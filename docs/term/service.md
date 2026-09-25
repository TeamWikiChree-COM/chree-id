# 用語: サービス連携

使いかたは [API](../API.md) と [サービス接続](../INTEGRATION.md) を参照。

| 語 | 意味 |
| --- | --- |
| OP (OpenID Provider) | ログインさせる側。ChreeID のこと |
| RP (Relying Party) | ログインしに来る側。DokuFarm などのサービス |
| クライアント | ChreeID に登録したサービス。`client_id` を持つ |
| confidential クライアント | `client_secret` を持つクライアント。サーバ間 API を使えるのはこちらだけ |
| public クライアント | `client_secret` を持たないクライアント (SPA やアプリ)。PKCE が必須 |
| 発行の許可 | サーバ間 API で ServiceAccount を発行してよいかの設定 (`can_provision`)。管理画面で許可する |
| sub | サービスに渡す利用者の識別子。サービスごとに違う値になり、ServiceAccount に属する |
| scope | サービスが要求できる情報の範囲。`openid`、`profile`、`email` |
| claims | ID Token や UserInfo で渡す個々の情報 (`name`、`email`、`email_verified` など) |
| サービス側の識別子 | サービスが自分の利用者を指す ID (`service_user_id`)。サーバ間 API ではこれで利用者を指定する |

## 信頼状態 (trust)

| 値 | 意味 |
| --- | --- |
| `official` | 公式に提供・運営しているサービス |
| `approved` | 確認・承認を受けたサービス |
| `unapproved` | 登録されているが未承認 |
| `disabled` | 利用を停止したサービス。認証に使えない |
