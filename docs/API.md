# API

ChreeID が外に出している口は2種類ある。

| 種類 | 使う人 | 場所 |
| --- | --- | --- |
| OIDC (OpenID Connect) | ブラウザで「ChreeID でログイン」させたいサービス | `/oauth/*`、`/.well-known/openid-configuration` |
| サーバ間 API | サービスのサーバから ChreeID を直接呼ぶ場合 | `/api/v1/*` |

サービスをつなぐときの全体の流れは [INTEGRATION.md](INTEGRATION.md) を参照。

## 仕様書の場所

サーバ間 API の詳しい仕様は OpenAPI で公開しており、こちらを正とする。

- 本番: https://id.wikichree.com/api-docs/v1
- ローカル: http://chreeid.test/api-docs/v1

| もの | URL |
| --- | --- |
| ドキュメント (ブラウザで読む) | `/api-docs/v1` |
| OpenAPI の定義 (JSON) | `/api/v1/openapi.json` |

定義はコードから生成している (`app/Modules/ApiDocs/`)。API を変えたら、こちらも合わせて直す。

## OIDC

設定は `/.well-known/openid-configuration` から自動で取れる。主な値は次のとおり。

| 項目 | 値 |
| --- | --- |
| フロー | 認可コードフロー (`response_type=code`) |
| PKCE | `S256` |
| scope | `openid` `profile` `email` |
| クライアント認証 | `client_secret_basic`、`client_secret_post`、`none` (public クライアント) |
| ID Token の署名 | RS256。公開鍵は `/oauth/jwks` |
| リフレッシュトークン | 未対応 |

| エンドポイント | パス |
| --- | --- |
| 認可 | `GET /oauth/authorize` |
| トークン | `POST /oauth/token` |
| UserInfo | `GET /oauth/userinfo` |
| 公開鍵 | `GET /oauth/jwks` |

`sub` はサービスごとに違う値になる。同じ人でも、サービスAとサービスBでは別の `sub` が返る。

## サーバ間 API

### 認証

confidential クライアントの `client_id` と `client_secret` で認証する。さらに、管理画面で「発行を許可」したクライアントでなければ呼べない (`403 access_denied`)。

### 主な操作

利用者はサービス側の識別子 (`serviceUserId`) で指定する。

| 操作 | メソッドとパス |
| --- | --- |
| ServiceAccount を発行する (何度呼んでも同じ `sub`) | `PUT /api/v1/service-accounts/{serviceUserId}` |
| 状態を見る | `GET /api/v1/service-accounts/{serviceUserId}` |
| 停止する | `DELETE /api/v1/service-accounts/{serviceUserId}` |
| パスワードを反映する | `PUT /api/v1/service-accounts/{serviceUserId}/password` |
| 移行用の URL を発行する | `POST /api/v1/service-accounts/{serviceUserId}/claim-tickets` |
| パスワードを照合する | `POST /api/v1/service-auth/password` |
| メールログインのリンクを送る / 使う | `POST /api/v1/service-auth/magic-link`、`.../consume` |

### エラーの形

失敗はすべて次の形で返る。`error_description` は `Accept-Language` の言語になる。

```json
{
  "error": "invalid_request",
  "error_description": "The service user id field is required.",
  "errors": { "service_user_id": ["The service user id field is required."] }
}
```

`errors` は入力エラー (422) のときだけ付く。
