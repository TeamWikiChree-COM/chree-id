# 開発環境構築

## 必要なもの

| 項目 | バージョン |
| --- | --- |
| PHP | 8.5 以上 (拡張 `pdo_pgsql`、`openssl`) |
| Composer | 2 |
| PostgreSQL | 18 |
| Node.js | 22 |

Windows では Laragon で動かしている (XAMPP は PHP 8 系の途中で止まっているため)。Apache の DocumentRoot は `public/`、ローカルの URL は `http://chreeid.test/`。

## 手順

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan chreeid:generate-key   # ID Token の署名鍵を .env に書き込む
php artisan migrate
npm run dev                        # フロントエンド (開発中はこれを起動しておく)
```

データベースが無ければ先に作る。

```bash
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE chreeid ENCODING 'UTF8';"
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

## ChreeID 固有の設定

`.env.example` にすべて書いてある。ローカルで触ることが多いものだけ挙げる。

| キー | 用途 |
| --- | --- |
| `CHREEID_SIGNING_KEY` | ID Token の署名鍵。`chreeid:generate-key` で作る |
| `CHREEID_ADMIN_EMAILS` | 管理画面に入れるアカウントのメールアドレス |
| `CHREEID_TURNSTILE_SITE_KEY` / `_SECRET_KEY` | ボット対策 (Cloudflare Turnstile)。空なら無効 |
| `CHREEID_BACKUP_*` | バックアップ。ローカルでは空のままでよい |
| `CHREEID_LOGO_URL` | ヘッダーなどに出すロゴ |

## よく使うコマンド

```bash
php artisan test --parallel          # テスト
php vendor/bin/phpstan analyse       # 静的解析
npm run typecheck                    # TypeScript の型検査
php artisan lang:build               # 翻訳辞書を変えたあと
php artisan chreeid:prune-tokens     # 期限切れトークンと、猶予を過ぎた退会アカウントを消す
```

### todo (任意)

よく使うコマンドは `todofile.json5` にまとめてあり、[Todofile](https://github.com/Pitan76/Todofile) があれば短く呼べる。無くても上のコマンドを直接打てば同じ。

```bash
composer global require pitan76/todofile   # 入れる (初回だけ)

todo setup      # 初回のセットアップ一式
todo check      # コミット前の確認をまとめて (書き換えはしない)
todo lang       # php artisan lang:build
todo phpdoc     # PHPDoc から HTML を作る (storage/doctum/html/index.html)。公開版は https://teamwikichree-com.github.io/chree-id/
```

ほかのタスクは `todofile.json5` を参照。

## うまく動かないとき

| 症状 | 原因と対処 |
| --- | --- |
| `Failed opening required '.../vendor/autoload.php'` | `composer install` が終わっていない。もう一度実行する |
| `could not find driver` (PDO) | `php.ini` で `pdo_pgsql` が無効か、php-cgi のプロセスが古い設定のまま残っている。`php-cgi.exe` を止めるか Apache を再起動する |
| CLI では動くのにブラウザで動かない | 上と同じ。CLI と FastCGI は別プロセスなので、`php.ini` の変更がブラウザ側にすぐ反映されない |
| `php -v` が 8.5 にならない | PATH で別の PHP が先に見つかっている。フルパスで 8.5 を指定するか、PATH を直したあとにエディタやターミナルを起動し直す |
| Composer が「PHP のバージョンが合わない」と言う | Composer を動かしている PHP が古い。8.5 の PHP で `composer.phar` を実行する |
