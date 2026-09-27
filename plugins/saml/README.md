# SAML

外部の SAML IdP (Entra ID、Google Workspace、Okta など) で ChreeID にログインできるようにする。
ChreeID は SP になる。ログイン画面と連携の設定画面に、ほかの外部 IdP と同じようにボタンが出る。

## 設定の手順

1. IdP に SP を登録する。SP のメタデータは `/plugins/saml/metadata` で出している
   - ACS (Assertion Consumer Service) の URL は `/plugins/saml/acs`、バインディングは HTTP-POST
   - Assertion に署名させる。署名の無い Assertion は受けない
   - NameID は persistent か emailAddress にする。transient は受けない (ログインのたびに変わり、同じ人を同じアカウントへ結べないため)
2. IdP から entityID・SSO の URL・署名用の証明書を受け取り、ChreeID の .env に書く

```
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

## メールの扱い

`SAML_IDP_TRUST_EMAIL` が false のあいだは、同じメールの既存アカウントがあってもそこへは紐付けず、ログインを断る。
既存アカウントの人は、先にそのアカウントでログインし、設定の「連携」から SAML を足してもらう。

true にすると自動で紐付く。社内の IdP のように、メールを IdP 側が発行・管理しているときだけにする。
確かめていない IdP で true にすると、他人のメールを名乗ったアカウントからその人のアカウントに入れてしまう。

## 受け取りの流れ

```
IdP ──POST──▶ /plugins/saml/acs        セッションを持たないルート。応答を5分だけ預け、GET へ回す
          ──GET──▶ /plugins/saml/acs?k=…   預けた応答を取り出し、検証して本体のログインへ渡す
```

POST を直接受けないのは、IdP からの POST がクロスサイトになり、SameSite=Lax のセッション Cookie が付かないため。
web ミドルウェアで受けると空のセッションが作られ、その Cookie で利用者のセッションを上書きしてしまう。

IdP から勝手に送られてくる応答 (IdP-initiated) は受けない。ChreeID が出した AuthnRequest への応答だけを受ける。
