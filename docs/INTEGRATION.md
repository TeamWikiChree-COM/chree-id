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

## 4. 退会時の削除依頼を受け取る (任意)

利用者が ChreeID を退会するとき、連携しているサービスごとに「削除する」か「解放する」かを選択させる。クライアント設定の「削除依頼の URL」を設定したサービスだけが「削除する」の対象になる。設定しないサービスは解放 (サービスのアカウントを残し、ChreeID の別のアカウントへ切り出す) になる。

ChreeID は、その URL へ `withdrawal_token` を `application/x-www-form-urlencoded` で POST する。`withdrawal_token` は OIDC Back-Channel Logout の `logout_token` と同じ形式の JWT で、ID Token と同じ鍵 (JWKS) で署名している。

| クレーム | 内容 |
| --- | --- |
| `iss` / `aud` | ChreeID の issuer と、サービスの `client_id` |
| `iat` / `exp` / `jti` | 発行日時、有効期限 (2分)、一意な ID |
| `sub` | OIDC で渡した `sub`。OIDC を通っていない利用者には無い |
| `service_user_id` | サーバ間 API で登録したサービス側の識別子。無い場合もある |
| `events` | `{"urn:chreeid:event:account-withdrawn": {"action": "check" または "delete"}}` |

ヘッダの `typ` は `withdrawal+jwt`。

- `check`: 退会画面を開いたときに送る。削除できるかを返すだけで、何も削除しない
- `delete`: 退会したときに送る。論理削除にとどめ、物理削除はサービス側の猶予を過ぎてから実施する。猶予は ChreeID の猶予日数以上にする

どちらにも、HTTP 200 で次の JSON を返す。

```json
{"accepted": true, "grace_days": 30}
{"accepted": false, "reason": "所有している Wiki を削除してから退会してください"}
```

- `accepted`: 削除できる (`delete` では受け付けた) か
- `reason`: 削除できない理由。利用者にそのまま表示する
- `grace_days`: 物理削除までの日数。任意。退会画面に表示する

`check` で `accepted: false` を返すと、そのサービスでは「削除する」を選択できなくなる。`delete` で断った場合は、利用者へメールで通知する。200 以外の応答や不正な JSON は届かなかったものとして扱い、`delete` は15分おきに1日間再送する。再送のたびに `jti` は変わるため、同じ利用者への依頼が重複して届いても問題がないように処理する。

## 注意

- `sub` はサービスごとに違う。サービスをまたいで同じ人かを `sub` で判定することはできない
- 同じ人が同じサービスに複数の ServiceAccount を持つことがある (統合した場合)。そのときは ChreeID の同意画面で本人に選ばせる
- `email_verified` が `true` でないアドレスを、到達確認済みとして扱わない
