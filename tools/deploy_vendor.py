#!/usr/bin/env python3
"""壊れた vendor ファイルだけを送り直す。

deploy.py は git 追跡ファイルしか送らないので vendor は対象外。
FTP で 9000 個以上を一度に送ると転送が途中で切れて 0 バイトのファイルが混ざり、
その状態は「存在はする」ため差分アップロードでは直らない。

どのファイルが壊れているかは _diag.php が vendor-manifest.txt と突き合わせて
サーバー側で判定済みなので、その出力を貼り付けて渡す。
FTP 越しに 9000 回サイズを問い合わせると現実的な時間で終わらないため、
このツールは自分では照合しない。

使い方:
    1. サーバーで _diag.php を開く
    2. 「存在しない」「サイズ違い」の行をテキストファイルに貼る (broken.txt など)
    3. python tools/deploy_vendor.py broken.txt --check
    4. python tools/deploy_vendor.py broken.txt

    パスを直接渡すこともできる:
    python tools/deploy_vendor.py --path vendor/laravel/framework/src/Illuminate/Pagination/CursorPaginator.php

接続設定は deploy.py と同じ tools/.deploy-env を使う。
"""

from __future__ import annotations

import argparse
import ftplib
import posixpath
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from deploy import fail, load_config, open_transport  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent

# _diag.php の出力行から vendor パスを拾う。
#   "    - vendor/…/CursorPaginator.php  (期待 5263 / 実際 0)"
#   "    - vendor/…/Sudo.php"
# 先頭の記号や末尾の注記は無視し、vendor/ で始まり .php などで終わる部分だけを取る
PATH_PATTERN = re.compile(r"(vendor/[A-Za-z0-9_./+-]+\.[A-Za-z0-9]+)")


def parse_paths(text: str) -> list[str]:
    """_diag.php の出力から重複を除いたパス一覧を作る。順序は保つ。"""
    found: list[str] = []
    seen: set[str] = set()

    for match in PATH_PATTERN.finditer(text):
        path = match.group(1)
        if path in seen: continue

        seen.add(path)
        found.append(path)

    return found


def remote_size(transport, remote: str) -> int | None:
    """送ったあとの確認用。存在しなければ None。"""
    ftp = getattr(transport, "ftp", None)
    if ftp is not None:
        try:
            ftp.voidcmd("TYPE I")  # SIZE はバイナリモードでないと通らないサーバーがある
            return ftp.size(remote)
        except ftplib.error_perm:
            return None

    sftp = getattr(transport, "sftp", None)
    if sftp is not None:
        try:
            return sftp.stat(remote).st_size
        except IOError:
            return None

    fail("対応していない転送方式です。")


def collect_targets(args: argparse.Namespace) -> list[str]:
    """引数から送るファイルの一覧を作り、ローカルに実在するものだけ返す。"""
    text = "\n".join(args.path)
    for source in args.source:
        text += "\n" + Path(source).read_text(encoding="utf-8", errors="replace")

    if not args.path and not args.source:
        text = sys.stdin.read()

    paths = parse_paths(text)
    if not paths: fail("送るファイルが1つも見つかりません。_diag.php の出力を渡してください。")

    targets = []
    for path in paths:
        if (ROOT / path).is_file():
            targets.append(path)
            continue

        print(f"  ローカルに無いので飛ばす: {path}")

    return targets


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("source", nargs="*", help="_diag.php の出力を貼ったテキストファイル（省略時は標準入力）")
    parser.add_argument("--path", action="append", default=[], help="パスを直接指定する（複数可）")
    parser.add_argument("--check", action="store_true", help="送らずに対象を表示する")
    parser.add_argument("--verify", action="store_true", help="送ったあとサイズを確認する")
    args = parser.parse_args()

    config = load_config()
    targets = collect_targets(args)
    base = config["FTP_DIR"].rstrip("/")

    print(f"対象: {len(targets)} ファイル")
    for path in targets:
        print(f"  {(ROOT / path).stat().st_size:>9,}  {path}")

    if args.check:
        print("--check なので送りません")
        return 0

    print(f"\n{config['FTP_HOST']}:{base} へ送ります")
    transport = open_transport(config)
    failed: list[str] = []
    try:
        for index, path in enumerate(targets, start=1):
            remote = f"{base}/{path}"
            data = (ROOT / path).read_bytes()

            transport.ensure_dir(posixpath.dirname(remote))
            transport.upload(data, remote)
            print(f"  [{index}/{len(targets)}] {path}")

            if not args.verify: continue

            actual = remote_size(transport, remote)
            if actual != len(data):
                failed.append(f"{path} (期待 {len(data)} / 実際 {actual})")
                print(f"      ← サイズが合いません: {actual}")
    finally:
        transport.close()

    if failed:
        print("\n送り直しても合わなかったファイル:")
        for line in failed: print(f"  - {line}")
        return 1

    print("\n完了しました。_diag.php をもう一度開いて確認してください。")
    return 0


if __name__ == "__main__":
    sys.exit(main())
