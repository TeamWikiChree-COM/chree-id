# デプロイ

push すると GitHub Actions (`.github/workflows/deploy.yml`) から `tools/deploy.py` が動き、サーバへ転送する。手でアップロードすることは基本的に無い。

## 何が送られるか

送るのは git で追跡しているファイルと、`.deploy-include` に挙げたパスだけ。`.gitignore` に入れたものはサーバに届かない。

| 届かないもの | 対処 |
| --- | --- |
| `.env` | サーバに手で置く。中身はローカルと別にする |
| `vendor/` | サーバ側で `composer install --no-dev` |

### `.deploy-include`

git で追跡していなくても毎回送るパスを、1行1件で書く。

フロントエンドのビルド成果物 (`public/build/`) は履歴に入れたくない。一方で、本番のサーバでは npm を動かせないのでサーバ側でもビルドできない。そこで CI がデプロイの直前に `npm run build` し、その成果物をリポジトリを通さず直接送っている。

CI は変更の有無にかかわらず毎回ビルドする。送らなかった回にサーバ側が古いまま残ると、誰かが気付くまでそのままになるため。

ビルド成果物を手でコミットする必要は無い。

## `[skip ci]` は使わない

GitHub Actions は push の先頭のコミットメッセージだけを見てスキップするかを決める。`[skip ci]` が付いたコミットが最後にあると、その push のデプロイが丸ごと飛ぶ。成功も失敗も表示されないので気付けない。

コミットメッセージに `[skip ci]` を書かないこと。

## デプロイされたか確かめる

未ログインで保護されたページを叩く。ルートがあれば `/login` への 302、無ければ 404 になる。

```bash
curl -s -o /dev/null https://id.wikichree.com/admin -w '%{http_code}'
```

## 本番の .env で必ず変えるもの

```ini
APP_ENV=production
APP_DEBUG=false
APP_KEY=              # 本番用に生成する
CHREEID_ISSUER=https://（本番の URL）
CHREEID_SIGNING_KEY=  # 本番用に別に生成する
```

署名鍵はローカルと別のものにする。同じ鍵だと、ローカルで発行した ID Token が本番でも通ってしまう。

## サーバ側の準備

- `storage/` と `bootstrap/cache/` を書き込めるようにする
- cron で `php artisan schedule:run` を毎分動かす。期限切れトークンの掃除 (`chreeid:prune-tokens`、毎日 4:00) はこれで動く。登録申し込みにはパスワードハッシュが入るので、放置しない

### 公開ディレクトリ

本番のサーバでは DocumentRoot を `public/` にできないため、プロジェクト直下に `index.php` と `.htaccess` を置いて `public/index.php` へ渡している。

この構成だと `vendor/` や `storage/` が Web から見えてしまうので、`.htaccess` で塞いでいる。DocumentRoot を `public/` に向けられるサーバなら、そちらのほうが安全。

## 起動しないとき

`Target class [view] does not exist` は、本当の原因ではないことが多い。起動に失敗した例外を表示しようとして、まだ `view` が使えずに二次的に落ちている。

次の順に確かめる。

1. `.env` はあるか (`APP_KEY` が空だとここで落ちる)
2. `storage/` と `bootstrap/cache/` に書き込めるか
3. `vendor/` は入っているか
4. `storage/logs/laravel.log` に本当の例外が出ていないか

一時的に `APP_DEBUG=true` にすると元の例外が見える。確かめたら必ず `false` に戻す。
