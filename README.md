# ChreeID

WikiChree.COM / DokuFarm / ModParks などの Chree 関連サービスを対象とした、共通認証基盤。

各サービスのアカウント・認証を一元化し、ChreeID を OpenID Provider として
サービス側は Relying Party になる。既存サービスの利用体験は可能な限り維持する。

> **設計・調査ドキュメントは別リポジトリにある。**
> `web-chree/memo/chreeid/` を参照。
>
> | ファイル | 内容 |
> | --- | --- |
> | `CHREE_ID.md` | 要件定義書 |
> | `RESEARCH.md` | 既存3サービスの調査結果と移行方針 |
> | `ARCHITECTURE.md` | アーキテクチャ設計（モジュール構成・レジストリ・拡張方針） |
> | `TUTORIAL.md` | 基礎実装の手順 |
> | `OVERVIEW.md` | 初期の概要メモ |

---

## 開発環境

Laragon 上で動かす。**Apache の DocumentRoot は `public/`**（Laragon が自動生成する vhost）。

| 項目 | 値 |
| --- | --- |
| URL | <http://chreeid.test/> |
| PHP | **8.5.10 (NTS)** — `E:\laragon\bin\php\php-8.5.10-nts-Win32-vs17-x64` |
| 実行方式 | Apache 2.4.68 + **mod_fcgid**（NTS のため mod_php ではない） |
| フレームワーク | Laravel 13 |
| DB | **PostgreSQL 18.2**（Laragon 同梱）。データベース名 `chreeid` |
| Node | 22（Laragon 同梱） |

### PHP は 8.5 を使う

Laragon には 8.3.33 (TS) も入っており、`etc/apache2/mod_php.conf` はそちらを指しているが、
`httpd.conf` が読み込んでいるのは **`fcgid.conf` のほう**なので、実際に動くのは 8.5.10。

CLI で叩くときも 8.5 を明示すること。**`php` と打つと別の PHP が出る環境なので注意。**

```bash
E:/laragon/bin/php/php-8.5.10-nts-Win32-vs17-x64/php.exe artisan ...
```

PHP 8.5 を選んだ理由と、XAMPP 側（8.4.12 / WikiChree・DokuFarm 用）を触らない理由は
`ARCHITECTURE.md` 6.3〜6.4 節にある。

### 有効化済みの拡張

`php.ini` で以下を有効にしてある（初期状態では PostgreSQL 系が無効）。

```ini
extension=pdo_pgsql
extension=pgsql
extension=zip
```

`curl` / `fileinfo` / `intl` / `mbstring` / `openssl` / `sodium` は既定で有効。

---

## セットアップ

```bash
PHP=E:/laragon/bin/php/php-8.5.10-nts-Win32-vs17-x64/php.exe

$PHP E:/laragon/bin/composer/composer.phar install
cp .env.example .env
$PHP artisan key:generate
$PHP artisan migrate
```

`.env` の DB 設定:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chreeid
DB_USERNAME=postgres
DB_PASSWORD=
```

データベースが無ければ作る。

```bash
"E:/laragon/bin/postgresql/postgresql/bin/psql.exe" -h 127.0.0.1 -U postgres -c "CREATE DATABASE chreeid ENCODING 'UTF8';"
```

---

## ディレクトリ方針

機能ごとの縦割りモジュールにし、モジュール内をレイヤードにする（`ARCHITECTURE.md` 2章）。

```text
app/Modules/
├── Identity/      … ChreeAccount, プロフィール, ULID発行, アカウント統合
├── Credential/    … Password, Passkey(WebAuthn), TOTP, MagicLink
├── Federation/    … 外部IdP(Google/GitHub)との連携 = ChreeID が RP 側
├── Provider/      … OIDC OP 本体 = ChreeID が OP 側
├── Registry/      … サービス登録・公式/承認状態
├── Linking/       … サービスアカウントとの紐付け・遅延移行
└── Audit/         … 監査ログ

各モジュール内: Domain / Application / Infrastructure / Http
```

**`Federation`（外部へログインしに行く）と `Provider`（サービスがログインしに来る）は別物。**
`OAuthService` のような、どちら側か分からない名前を作らないこと。

依存は内側にしか向けない。`Domain/` は Eloquent も Request も知らない。

---

## 開発上の注意

1. **Eloquent モデルは `Infrastructure/` に置く。** `Domain/` からは触らない
2. **認証成立の判定（`AuthenticationPolicy`）は拡張不可。** 全ての認証経路がここを通る
3. **OIDC のプロトコル部分はプラグイン化しない。** 仕様が RFC で固定されているため
4. 認証方式・scope・外部IdP の追加は**レジストリに1行**（`ARCHITECTURE.md` 4章）
5. 表示文字列をハードコードしない。翻訳は JSON を正とし、PHP はビルド成果物にする（同 8章）
6. コーディング規約は `AGENTS.md` に従う（1ファイル300行 / 1メソッド40行 / `{` は同一行）

---

## 詰まったときのメモ

| 症状 | 原因と対処 |
| --- | --- |
| `Failed opening required '.../vendor/autoload.php'` | `composer install` が未完了。再実行する |
| `could not find driver`（PDO） | `php.ini` の `pdo_pgsql` が無効、または **php-cgi プロセスが古い設定のまま常駐している**。`fcgid.conf` は `FcgidMaxRequestsPerProcess 0` なのでプロセスが再生成されない。`php-cgi.exe` を停止するか Apache を再起動する |
| CLI では動くのにブラウザで動かない | 上と同じ。CLI と FastCGI で別プロセスなので、`php.ini` の変更はブラウザ側に即時反映されない |
| `php -v` が 8.5 でない | PATH の既定は XAMPP 側（8.4.12）。フルパスで 8.5 を指定する |

---

## デプロイ

`.github/workflows/deploy.yml` から `tools/deploy.py` が動く。
**git 追跡ファイルしか転送しない**ので、`.gitignore` に入れたものはサーバーに届かない。

### 届かないもの（毎回ここで詰まる）

| | 対処 |
| --- | --- |
| `.env` | **手動で置く。** 中身はローカルと別にすること |
| `vendor/` | サーバー側で `composer install --no-dev` |
| `public/build/` | **コミットする。** `.gitignore` から外してある |

`public/build` をコミットするのは、この転送方式と噛み合わせるため。
ただし **CI が自分で `npm run build` して `public/build` をコミット・push する**ので、
手でやる必要はない（やっても構わないが、CI 側のコミットと衝突しやすい）。

### `[skip ci]` を push の最後に置かない

CI がアセットをコミットするときのメッセージは
`chore: build frontend assets [skip ci]` で、`[skip ci]` は
**CI が自分の push で自分を再起動しないための札**。人が真似するものではない。

GitHub Actions は **push の HEAD コミットのメッセージ**だけを見て判定するので、
手でビルド成果物をコミットして最後に置くと、その push のデプロイが丸ごと飛ぶ。
しかも成功も失敗も出ないので気付けない。実際にこれで2回、
「push したのにデプロイされない」状態になった。

デプロイされていないか確かめるには、未ログインで保護ページを叩く。
ルートがあれば `/login` へ 302、無ければ 404 になる。

```
curl -s -o /dev/null https://id.wikichree.com/admin -w '%{http_code}'
```

### 本番の .env で必ず変えるもの

```ini
APP_ENV=production
APP_DEBUG=false
APP_KEY=              # 本番用に生成する
CHREEID_ISSUER=https://（本番のURL）
CHREEID_SIGNING_KEY=  # 本番用に別途生成する
```

**署名鍵はローカルと別のものにする。** 同じ鍵だと、ローカルで発行した ID Token が本番でも通る。

### パーミッション

`storage/` と `bootstrap/cache/` が書き込み可能であること。

### 公開ディレクトリについて

DocumentRoot を `public/` にできないサーバーのため、プロジェクト直下に `index.php` と
`.htaccess` を置いて `public/index.php` へ渡している。

この構成では **`vendor/` や `storage/` が Web から見えてしまう**ので、
`.htaccess` で明示的に塞いでいる。DocumentRoot を `public/` に向けられるなら、
そちらのほうが安全。

### 起動しないときの読み方

`Target class [view] does not exist` は**本当の原因ではない**ことが多い。
起動に失敗した例外を表示しようとして、まだ `view` が使えず二次的に落ちている。

```text
1. .env はあるか（APP_KEY が空だとここで落ちる）
2. storage/ と bootstrap/cache/ は書き込めるか
3. vendor/ は入っているか
4. storage/logs/laravel.log に本当の例外が出ていないか
```

一時的に `APP_DEBUG=true` にすると元の例外が見える。**確認したら必ず false に戻す。**
