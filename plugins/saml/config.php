<?php

// ChreeID が SP として、外部の SAML IdP (Entra ID、Google Workspace など) でログインを受ける設定。
// idp の entity_id・sso_url・x509cert が揃ったときだけログイン画面にボタンが出る。
return [
    'sp' => [
        // 空なら /plugins/saml/metadata の URL を使う
        'entity_id' => env('SAML_SP_ENTITY_ID', ''),
        // AuthnRequest に署名するときだけ。PEM の中身 (BEGIN/END の行は有っても無くてもよい)
        'x509cert' => env('SAML_SP_CERT', ''),
        'private_key' => env('SAML_SP_PRIVATE_KEY', ''),
    ],
    'idp' => [
        // ログイン画面のボタンに出す名前 (社名など)。空なら SAML
        'label' => env('SAML_IDP_LABEL', ''),
        'entity_id' => env('SAML_IDP_ENTITY_ID', ''),
        'sso_url' => env('SAML_IDP_SSO_URL', ''),
        'x509cert' => env('SAML_IDP_CERT', ''),
        // 空なら NameID をメールとして使う (NameID の形式が emailAddress のときだけ)
        'email_attribute' => env('SAML_IDP_EMAIL_ATTRIBUTE', ''),
        'name_attribute' => env('SAML_IDP_NAME_ATTRIBUTE', ''),
        // IdP がメールの持ち主を確かめている (社内の IdP など) ときだけ true にする。
        // true だと、同じメールの既存アカウントへ自動で紐付ける。確かめていない IdP で
        // true にすると、他人のメールを名乗ったアカウントから乗っ取れてしまう
        'trust_email' => (bool) env('SAML_IDP_TRUST_EMAIL', false),
    ],
];
