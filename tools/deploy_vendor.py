#!/usr/bin/env python3
"""vendor をサーバと突き合わせて、欠けているファイルだけ送り直す。

deploy.py は git 追跡ファイルしか送らないため、vendor は対象外になる。
FTP で 9000 個以上のファイルを送ると転送が途中で切れて 0 バイトのファイルが
混ざることがあり、その状態は差分アップロードでは直らない (存在はするため)。

このツールはサーバ側のサイズを1つずつ問い合わせ、
  - 存在しない
  - サイズが違う (0 バイトを含む)
ものだけを送り直す。

使い方:
    python tools/deploy_vendor.py --check     # 差分を数えるだけ
    python tools/deploy_vendor.py             # 壊れているものだけ送る
    python tools/deploy_vendor.py --full      # 全件送り直す
    python tools/deploy_vendor.py --no-dev    # 開発用パッケージを送らない

接続設定は deploy.py と同じ tools/.deploy-env を使う。
"""

from __future__ import annotations

import argparse
import ftplib
import posixpath
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from deploy import fail, load_config, open_transport  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
VENDOR = ROOT / "vendor"

# 本番に要らないもの。--no-dev のときだけ除外する
DEV_PACKAGES = (
    "vendor/phpunit/",
    "vendor/phpstan/",
    "vendor/larastan/",
    "vendor/psy/",
    "vendor/mockery/",
    "vendor/fakerphp/",
    "vendor/nunomaduro/collision/",
    "vendor/sebastian/",
    "vendor/phar-io/",
    "vendor/theseer/",
    "vendor/myclabs/",
    "vendor/doctrine/instantiator/",
)


def local_files(no_dev: bool) -> dict[str, int]:
    """vendor 配下のファイルとサイズ。パスはプロジェクトからの相対 (posix)。"""
    if not VENDOR.is_dir():
        fail("vendor がありません。先に composer install を実行してください。")

    files: dict[str, int] = {}
    for path in VENDOR.rglob("*"):
        if not path.is_file():
            continue

        rel = path.relative_to(ROOT).as_posix()
        if no_dev and rel.startswith(DEV_PACKAGES):
            continue

        files[rel] = path.stat().st_size

    return files


def remote_size(transport, remote: str) -> int | None:
    """サーバ上のサイズ。無ければ None。"""
    ftp = getattr(transport, "ftp", None)
    if ftp is not None:
        try:
            # SIZE はバイナリモードでないと通らないサーバがある
            ftp.voidcmd("TYPE I")
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


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--check", action="store_true", help="送らずに差分を表示する")
    parser.add_argument("--full", action="store_true", help="照合せず全件送る")
    parser.add_argument("--no-dev", action="store_true", help="開発用パッケージを対象外にする")
    parser.add_argument("--limit", type=int, default=0, help="送る件数の上限 (0 は無制限)")
    args = parser.parse_args()

    config = load_config()
    files = local_files(args.no_dev)
    base = config["FTP_DIR"].rstrip("/")

    print(f"ローカルの vendor: {len(files)} ファイル")
    print(f"転送先: {config['FTP_HOST']}:{base}")

    transport = open_transport(config)
    try:
        if args.full:
            targets = sorted(files)
            print(f"全件送ります: {len(targets)} ファイル")
        else:
            print("サーバと照合しています (件数が多いので時間がかかります)...")
            missing: list[str] = []
            mismatch: list[str] = []

            for index, rel in enumerate(sorted(files), start=1):
                if index % 500 == 0:
                    print(f"  {index}/{len(files)} 照合済み")

                size = remote_size(transport, f"{base}/{rel}")
                if size is None:
                    missing.append(rel)
                elif size != files[rel]:
                    mismatch.append(rel)

            print(f"  存在しない: {len(missing)}")
            print(f"  サイズ違い: {len(mismatch)}")
            for rel in mismatch[:20]:
                print(f"    - {rel} (期待 {files[rel]})")

            targets = missing + mismatch

        if args.limit:
            targets = targets[: args.limit]

        if not targets:
            print("送るものはありません。vendor は揃っています。")
            return 0

        if args.check:
            print(f"--check なので送りません ({len(targets)} 件)")
            return 0

        print(f"{len(targets)} 件を送ります")
        for index, rel in enumerate(targets, start=1):
            remote = f"{base}/{rel}"
            transport.ensure_dir(posixpath.dirname(remote))
            transport.upload((ROOT / rel).read_bytes(), remote)

            if index % 100 == 0 or index == len(targets):
                print(f"  {index}/{len(targets)}")

        print("完了しました。_diag.php で確認してください。")
    finally:
        transport.close()

    return 0


if __name__ == "__main__":
    sys.exit(main())
