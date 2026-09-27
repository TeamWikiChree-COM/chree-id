# 起動構成

開発サーバーの立ち上げ方と、IDE から使える実行構成の一覧。初回の環境構築は [SETUP.md](SETUP.md) を参照。

## 開発サーバー

`php tools/dev-open.php` がまとめて面倒を見る。IDE の「開発サーバー」もこれを呼んでいるだけ。

1. `.env` の `APP_URL` が応答しなければ Laragon を起動して待つ
2. Vite (ポート 5173) が止まっていれば `npm run dev` を起動する
3. 準備ができたらブラウザで開く

Vite がすでに動いていれば、ブラウザを開くだけで終わる。止めると Vite も止まる。

Laragon が無い環境 (Linux など) では、`APP_URL` が応答しなければ代わりに `php artisan dev` を起動して `http://127.0.0.1:8000` を開く。

| 環境 | Web サーバー | 開く URL |
| --- | --- | --- |
| Windows + Laragon | Laragon の Apache | `APP_URL` (`http://chreeid.test`) |
| Laragon なし、自前のサーバーが `APP_URL` で動いている | そのサーバー | `APP_URL` |
| Laragon なし、サーバーも無い | `php artisan serve` | `http://127.0.0.1:8000` |

どの場合も PostgreSQL は別に起動しておく。

### php artisan dev を使うとき

`artisan serve` (PHP の組み込みサーバー)、`queue:listen`、Vite を一度に起動する。`pail` (ログ表示) は Windows では動かないので起動されない。

- OIDC の issuer やリダイレクト先は `APP_URL` から作られるので、`.env` の `APP_URL` を `http://127.0.0.1:8000` に合わせる。ずれていると警告を出す
- 組み込みサーバーは `.htaccess` を読まない
- キューのワーカーも動くので、キューに積む処理を試すときはこちらが本番に近い

## PhpStorm

実行構成は `.idea/runConfigurations/` に入っていて、プロジェクトを開けば右上の一覧に出る。PHP のものはプロジェクトに設定した PHP インタプリタで動く。

| 名前 | フォルダ | 中身 |
| --- | --- | --- |
| 開発サーバー | - | `php tools/dev-open.php` |
| artisan dev (serve + queue + Vite) | - | `php artisan dev --inline` |
| テスト (並列) | 品質 | `php artisan test --parallel` |
| PHPStan | 品質 | `php vendor/bin/phpstan analyse --memory-limit=1G` |
| Pint (整形) | 品質 | `php vendor/bin/pint` |
| Vite ビルド | フロント | `npm run build` |
| 型チェック (tsc) | フロント | `npm run typecheck` |
| lang:build | Artisan | `php artisan lang:build` |
| migrate | Artisan | `php artisan migrate` |

1つのテストだけ動かすときは、テストクラスやメソッドの横の実行ボタンを使う。

`.idea/` のうち git に入れているのは実行構成、インスペクション、辞書、Laravel Idea の設定だけ。インタプリタやソースルートは各自の環境で設定する。

## VS Code

同じものを `.vscode/tasks.json` にタスクとして入れている。コマンドパレットの「タスク: タスクの実行」から選ぶ。

- 開発サーバー: `Ctrl+Shift+B` (既定のビルドタスク)
- テスト (並列): 既定のテストタスク

## IDE を使わないとき

プロジェクトのルートで実行する。

```bash
php tools/dev-open.php       # 開発サーバー (上の説明どおり)
php artisan dev              # serve + queue + Vite をまとめて起動
npm run dev                  # Vite だけ (Laragon などで Web サーバーが動いているとき)
php artisan serve            # Web サーバーだけ
```

テストや静的解析は、上の表の「中身」をそのまま実行すればよい。
