"""deploy.py 用。サブモジュール (plugins/wiki-hub など) の中のファイルを、差分アップロードの対象に加える。

親リポジトリの git ls-files と git diff には、サブモジュールは「指しているコミット」の 1 行しか出ない。
そのままだと中のファイルが送られないので、サブモジュールの中で同じ計算をしてパスを親から見た形に直す。
"""

from __future__ import annotations

import subprocess
from pathlib import Path

GITLINK_MODE = "160000"


def _git(cwd: Path, *args: str) -> str:
    result = subprocess.run(
        ["git", "-c", "core.quotepath=false", *args],
        cwd=cwd, capture_output=True, text=True, encoding="utf-8", errors="replace",
    )
    if result.returncode != 0:
        raise RuntimeError(f"git {' '.join(args)} ({cwd}) が失敗しました\n{result.stderr.strip()}")
    return result.stdout


def _has_commit(cwd: Path, sha: str) -> bool:
    result = subprocess.run(["git", "cat-file", "-e", f"{sha}^{{commit}}"], cwd=cwd, capture_output=True)
    return result.returncode == 0


def submodules(root: Path, rev: str) -> dict[str, str]:
    """{サブモジュールのパス: 指しているコミット} を返す。"""
    found = {}
    for line in _git(root, "ls-tree", "-r", rev).splitlines():
        meta, _, path = line.partition("\t")
        parts = meta.split()
        if len(parts) == 3 and parts[0] == GITLINK_MODE:
            found[path] = parts[2]
    return found


def files(root: Path, path: str, sha: str) -> list[str]:
    """サブモジュールのコミットに含まれるファイルを、親から見たパスで返す。"""
    names = _git(root / path, "ls-tree", "-r", "--name-only", sha).splitlines()
    return [f"{path}/{name}" for name in names if name]


def changes(root: Path, path: str, old: str | None, new: str | None) -> tuple[dict[str, str], set[str]]:
    """サブモジュールの差分を ({パス: 状態}, 削除するパス) で返す。

    前回のコミットが手元に無いとき (浅い clone など) は、差分がとれないので全件を送る。
    """
    if old == new:
        return {}, set()
    if new is None:
        return {}, set(files(root, path, old)) if old and _has_commit(root / path, old) else set()
    if old is None or not _has_commit(root / path, old):
        return {p: "A" for p in files(root, path, new)}, set()

    upload: dict[str, str] = {}
    delete: set[str] = set()
    for line in _git(root / path, "diff", "--name-status", "--no-renames", old, new).splitlines():
        status, _, name = line.partition("\t")
        if not name.strip():
            continue
        full = f"{path}/{name.strip()}"
        if status.startswith("D"):
            delete.add(full)
        else:
            upload[full] = status.strip()[:1]
    return upload, delete


def blob(root: Path, subs: dict[str, str], path: str) -> bytes | None:
    """サブモジュールの中のファイルなら、その中身を返す。サブモジュールの外なら None。"""
    for sub, sha in subs.items():
        if path.startswith(sub + "/"):
            result = subprocess.run(
                ["git", "show", f"{sha}:{path[len(sub) + 1:]}"], cwd=root / sub, capture_output=True
            )
            if result.returncode != 0:
                raise RuntimeError(f"git show {sha}:{path} が失敗しました\n"
                                   f"{result.stderr.decode('utf-8', 'replace').strip()}")
            return result.stdout
    return None
