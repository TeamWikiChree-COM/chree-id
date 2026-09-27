<?php

// ChreeID が SP として、外部の SAML IdP (Entra ID、Google Workspace など) でログインを受ける設定。
// idp の entity_id、sso_url、x509cert が揃ったときだけログイン画面にボタンが出る。
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
        // IdP がメールの持ち主を確かめているときだけ true。そうでない IdP で true にすると乗っ取られる (README)
        'trust_email' => (bool) env('SAML_IDP_TRUST_EMAIL', false),
    ],

    // ここから下は、ChreeID が IdP として SAML のサービス (SP) にログインさせる側。
    // 鍵は php artisan saml:idp-key で作る
    'signing' => [
        'key_path' => env('SAML_SIGNING_KEY_PATH', storage_path('saml/idp.key')),
        'certificate_path' => env('SAML_SIGNING_CERT_PATH', storage_path('saml/idp.crt')),
    ],
];
