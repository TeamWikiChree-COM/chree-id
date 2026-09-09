# ChreeID
WikiChree.COM / DokuFarm / ModParks などのサービスを対象とした共通認証基盤サービス

各サービスのアカウント/認証を一元化し、ChreeIDをOpenID Providerとし、サービス側はRelying Partyになる。<br/>
既存サービスの利用体験は可能な限り維持する。

## 開発環境
Laragon上(XAMPPはPHP8あたりでとまっているため代替)で動かす。ApacheのDocumentRootは`public/`である。

| 項目 | 値 |
| --- | --- |
| URL | <http://chreeid.test/> |
| PHP | 8.5.10 (NTS) |
| 実行方式 | Apache 2.4.68 + mod_fcgid（NTS のため mod_php ではない） |
| フレームワーク | Laravel 13 |
| DB | PostgreSQL 18.2 `chreeid` |
| Node | 22 |

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

データベースがなければ作成する。

```bash
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE chreeid ENCODING 'UTF8';"
```

---

## ディレクトリ方針

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

`Federation`（外部へログインしに行く）と `Provider`（サービスがログインしに来る）は別物。
`OAuthService` のような、どちら側か分からない名前を作らないこと。

依存は内側にしか向けない。`Domain/` は Eloquent も Request も知らない。

## FAQ

| 症状 | 原因と対処 |
| --- | --- |
| `Failed opening required '.../vendor/autoload.php'` | `composer install` が未完了。再実行する |
| `could not find driver`（PDO） | `php.ini` の `pdo_pgsql` が無効、または php-cgi プロセスが古い設定のまま常駐している。`fcgid.conf` は `FcgidMaxRequestsPerProcess 0` なのでプロセスが再生成されない。`php-cgi.exe` を停止するか Apache を再起動する |
| CLI では動くのにブラウザで動かない | 上と同じ。CLI と FastCGI で別プロセスなので、`php.ini` の変更はブラウザ側に即時反映されない |
| `php -v` が 8.5 でない | PATH の既定は XAMPP 側（8.4.12）。フルパスで 8.5 を指定する |

## デプロイ

`.github/workflows/deploy.yml` から `tools/deploy.py` が動く。
転送するのは git 追跡ファイルと、`.deploy-include` に挙げたパスだけ。
それ以外は `.gitignore` に入れた時点でサーバーに届かない。

### 届かないもの

| | 対処 |
| --- | --- |
| `.env` | 手動で置く。 中身はローカルと別にすること |
| `vendor/` | サーバー側で `composer install --no-dev` |

`public/build/` は git 追跡外だが、`.deploy-include` に書いてあるので毎回送られる。
CI がデプロイ直前に `npm run build` した成果物を、リポジトリを経由せず直接転送している。

### `.deploy-include`

`.deploy-ignore` の逆。git 追跡外でも毎回送るパスを 1 行 1 件で書く。

ビルド成果物を履歴に入れたくない一方で、CoreServer では npm を回せないので
サーバ側でビルドもできない。その板挟みを解くための口。
差分計算は git の履歴を見るため追跡外のファイルは差分に現れず、
ここに挙げたものは毎回まるごと送られる。

CI は変更の有無にかかわらず毎回ビルドする。送らなかった回にサーバ側が
取り残されると、次に誰かが気付くまで古いままになるため。

### `[skip ci]` は使わない
GitHub Actions は push の HEAD コミットのメッセージだけを見て判定するので、
これが付いたコミットを最後に置くと、その push のデプロイが丸ごと飛ぶ。
しかも成功も失敗も出ないので気付けない。実際にこれで2回、
「push したのにデプロイされない」状態になった。

コミットメッセージに `[skip ci]` を書かないこと。
ビルド成果物を手でコミットする必要も、もう無い。

デプロイされたか確かめるには、未ログインで保護ページを叩く。
ルートがあれば `/login` へ 302、無ければ 404 になる。

```bash
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

署名鍵はローカルと別のものにする。 同じ鍵だと、ローカルで発行した ID Token が本番でも通る。

### パーミッション

`storage/` と `bootstrap/cache/` が書き込み可能であること。

### 公開ディレクトリについて

DocumentRoot を `public/` にできないサーバーのため、プロジェクト直下に `index.php` と
`.htaccess` を置いて `public/index.php` へ渡している。

この構成では `vendor/` や `storage/` が Web から見えてしまうので、
`.htaccess` で明示的に塞いでいる。DocumentRoot を `public/` に向けられるなら、
そちらのほうが安全。

### 起動しないときの読み方

`Target class [view] does not exist` は本当の原因ではないことが多い。
起動に失敗した例外を表示しようとして、まだ `view` が使えず二次的に落ちている。

```text
1. .env はあるか（APP_KEY が空だとここで落ちる）
2. storage/ と bootstrap/cache/ は書き込めるか
3. vendor/ は入っているか
4. storage/logs/laravel.log に本当の例外が出ていないか
```

一時的に `APP_DEBUG=true` にすると元の例外が見える。確認したら必ず false に戻す。
