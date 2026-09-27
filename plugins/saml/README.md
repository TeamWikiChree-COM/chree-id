# SAML

ChreeID を SAML に対応させる。向きが2つあり、それぞれ別に使える。

| 向き | 中身 | コード |
| --- | --- | --- |
| 外部の SAML IdP で ChreeID にログインする | ChreeID が SP。Entra ID、Google Workspace、Okta などのアカウントで入れる | `src/Sp/` |
| ChreeID で SAML のサービスにログインする | ChreeID が IdP。SAML しか話せないサービス (SP) に ChreeID のアカウントで入れる | `src/Idp/` |

## 外部の SAML IdP で ChreeID にログインする

ログイン画面と連携の設定画面に、ほかの外部 IdP と同じようにボタンが出る。

### 設定の手順

1. IdP に SP を登録する。SP のメタデータは `/plugins/saml/metadata` で出している
   - ACS (Assertion Consumer Service) の URL は `/plugins/saml/acs`、バインディングは HTTP-POST
   - Assertion に署名させる。署名の無い Assertion は受けない
   - NameID は persistent か emailAddress にする。transient は受けない (ログインのたびに変わり、同じ人を同じアカウントへ結べないため)
2. IdP から entityID・SSO の URL・署名用の証明書を受け取り、ChreeID の .env に書く

```
SAML_IDP_LABEL=              ボタンに出す名前 (社名など)。空なら SAML
SAML_IDP_ENTITY_ID=          IdP の entityID
SAML_IDP_SSO_URL=            IdP の SSO の URL (HTTP-Redirect)
SAML_IDP_CERT=               IdP の署名用の証明書 (PEM)
SAML_IDP_EMAIL_ATTRIBUTE=    メールが入る属性名。空なら NameID (emailAddress 形式のときだけ) を使う
SAML_IDP_NAME_ATTRIBUTE=     表示名が入る属性名。空なら取らない
SAML_IDP_TRUST_EMAIL=false   IdP がメールの持ち主を確かめているときだけ true

SAML_SP_ENTITY_ID=           空なら /plugins/saml/metadata の URL
SAML_SP_CERT=                AuthnRequest に署名するときだけ。証明書と鍵の両方が要る
SAML_SP_PRIVATE_KEY=
```

IdP の3つが揃うまでボタンは出ない。

### メールの扱い

`SAML_IDP_TRUST_EMAIL` が false のあいだは、同じメールの既存アカウントがあってもそこへは紐付けず、ログインを断る。
既存アカウントの人は、先にそのアカウントでログインし、設定の「連携」から SAML を足してもらう。

true にすると自動で紐付く。社内の IdP のように、メールを IdP 側が発行・管理しているときだけにする。
確かめていない IdP で true にすると、他人のメールを名乗ったアカウントからその人のアカウントに入れてしまう。

### 受け取りの流れ

```
IdP ──POST──▶ /plugins/saml/acs        セッションを持たないルート。応答を5分だけ預け、GET へ回す
          ──GET──▶ /plugins/saml/acs?k=…   預けた応答を取り出し、検証して本体のログインへ渡す
```

POST を直接受けないのは、IdP からの POST がクロスサイトになり、SameSite=Lax のセッション Cookie が付かないため。
web ミドルウェアで受けると空のセッションが作られ、その Cookie で利用者のセッションを上書きしてしまう。

IdP から勝手に送られてくる応答 (IdP-initiated) は受けない。ChreeID が出した AuthnRequest への応答だけを受ける。

## ChreeID で SAML のサービスにログインする

サービスは OIDC のサービスと同じく `oauth_clients` に登録し、SAML の設定だけをこのプラグインのテーブル (`saml_service_providers`) に持つ。
信頼状態・同意の省略・サービスアカウントは OIDC と共通なので、同じサービスに OIDC と SAML の両方で入っても同じサービスアカウントになる。

### 設定の手順

設定は管理画面の「SAML」(`/plugins/saml/admin`) で行う。同じことを artisan コマンドでもできる。

1. 管理画面の「マイグレーション」で、`saml_service_providers` の表を作る
2. 署名の鍵を作る。OIDC の鍵とは別の鍵で、証明書は SP 側に登録される
   - 画面の「鍵を作る」か、`php artisan saml:idp-key`
   - サーバで openssl が使えず作れないときは、手元で作った鍵と証明書 (PEM) を画面から取り込む
   - 置き場所は `storage/saml/idp.key` と `idp.crt`。変えるなら `SAML_SIGNING_KEY_PATH`・`SAML_SIGNING_CERT_PATH` を書く
   - 作り直すと、登録済みのすべての SP でメタデータの読み直しが要る
3. サービスを本体に登録する (管理画面の「接続サービス」)。OIDC を使わないなら redirect_uri は空でよい
4. 画面の「サービスを足す」で SAML の設定を足す (`php artisan saml:sp-add <client_id> <entityID> <ACS の URL>` でもよい)
   - SP の証明書を入れると、その SP からの AuthnRequest は署名を必ず確かめる
   - 外しても、サービスそのものとサービスアカウントは残る
5. SP に ChreeID の IdP メタデータを登録してもらう。`/plugins/saml/idp/metadata` で出している

手元で鍵を作るときの例:

```
openssl req -x509 -newkey rsa:3072 -nodes -keyout idp.key -out idp.crt -days 3650 -subj "/CN=id.example.com"
```

### SP に渡すもの

| 項目 | 値 |
| --- | --- |
| NameID | persistent 形式で、OIDC の sub と同じ値 |
| 属性 | スコープの範囲の OIDC のクレームを、同じ名前で渡す (email、email_verified、name など) |
| 署名 | Assertion に署名する (RSA-SHA256) |

### 受け取りの流れ

```
SP ──AuthnRequest──▶ /plugins/saml/idp/sso      HTTP-Redirect はそのまま、HTTP-POST は預けて GET に回す
                     SP の特定・ACS の照合・署名の確認
                  ──▶ 本体 /authorize/pending/…  ログイン・同意・サービスアカウントの選択
                  ──▶ /plugins/saml/idp/resume   署名した Response を SP の ACS へ自動で POST する
```

ACS は登録したものだけに送る。要求に別の ACS が書いてあれば断る。署名の無い要求で Response を別の場所へ送らせないため。
受けられない要求には、SP へエラーの Response を返さず画面で伝える。

### まだ無いもの

- シングルログアウト
- IdP から始めるログイン (IdP-initiated)
