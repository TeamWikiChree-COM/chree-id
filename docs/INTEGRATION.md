# サービス接続

新しいサービス (または既存のサービス) を ChreeID につなぐときの流れ。API の細かい形は [API.md](API.md) と OpenAPI を参照。

## 1. クライアントを登録する

管理画面 (`/admin/clients`) から登録するか、コマンドで登録する。

```bash
php artisan chreeid:register-client "サービス名" https://example.com/callback \
    --scopes="openid profile email" --trust=unapproved
```

| 項目 | 意味 |
| --- | --- |
| リダイレクト先 | ログイン後に戻す URL。登録したもの以外には戻さない |
| `--trust` | `official` / `approved` / `unapproved` / `disabled`。同意画面の出し方などが変わる |
| `--public` | client_secret を持たないクライアント (SPA やアプリ)。PKCE が必須になる |

サーバ間 API を使うなら、confidential クライアントにしたうえで、管理画面で発行を許可する。

## 2. つなぎ方を選ぶ

サービスの事情によって、つなぎ方は2通りある。組み合わせてもよい。

### A. 「ChreeID でログイン」ボタンを置く (OIDC)

新しく作るサービスや、ログイン画面を変えてよいサービス向け。一般的な OIDC クライアントライブラリがそのまま使える。

1. 利用者を `/oauth/authorize` へ送る
2. 戻ってきた認可コードを `/oauth/token` で ID Token に交換する
3. ID Token の `sub` をサービス側の利用者と結び付ける

### B. 画面はそのままで、照合先だけ ChreeID にする (サーバ間 API)

既存のログインフォームを変えたくないサービス向け。利用者は ChreeID の存在に気付かない。

1. 既存の利用者ごとに `PUT /api/v1/service-accounts/{serviceUserId}` で ServiceAccount を発行する。移行元のパスワードハッシュを渡せば、利用者はそのまま同じパスワードで入れる
2. ログインフォームに入力されたメールとパスワードを `POST /api/v1/service-auth/password` で照合する
3. 返ってきた `sub` でログインさせる

2段階認証を有効にしている利用者は、この口ではログインを完了できない (`second_factor_required`)。その場合は A の OIDC へ誘導する。

## 3. 移行の入り口を用意する (任意)

ServiceAccount のままでも使い続けられるが、本人が望めば UserAccount へ移せる。

1. サービスの設定画面などから `POST /api/v1/service-accounts/{serviceUserId}/claim-tickets` を呼ぶ
2. 返ってきた `claim_url` へ利用者を送る
3. 利用者は ChreeID の画面で、新しい UserAccount を作るか (移行)、既にある UserAccount に寄せるか (統合) を選ぶ

移行しても `sub` は変わらないので、サービス側で結び付けを直す必要は無い。用語は [ACCOUNTS.md](ACCOUNTS.md) を参照。

## 注意

- `sub` はサービスごとに違う。サービスをまたいで同じ人かを `sub` で判定することはできない
- 同じ人が同じサービスに複数の ServiceAccount を持つことがある (統合した場合)。そのときは ChreeID の同意画面で本人に選ばせる
- `email_verified` が `true` でないアドレスを、到達確認済みとして扱わない
