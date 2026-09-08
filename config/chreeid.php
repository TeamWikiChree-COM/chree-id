<?php

return [
    /*
     * OIDC の issuer。ID Token の iss クレームとディスカバリの base になる。
     * RP 側は完全一致で検証するので、末尾スラッシュを付けない。
     */
    'issuer' => env('CHREEID_ISSUER', env('APP_URL')),

    /*
     * ID Token の署名鍵 (RS256 の秘密鍵)。
     * 改行を .env に書けないので base64 で入れる。生成は chreeid:generate-key コマンド。
     */
    'signing_key' => env('CHREEID_SIGNING_KEY'),

    /* ID Token の寿命(秒) */
    'id_token_ttl' => (int) env('CHREEID_ID_TOKEN_TTL', 3600),

    /*
     * Cloudflare Turnstile。両方が入っているときだけ有効になる。
     * 未設定の環境 (ローカルや CI) では検証を丸ごと飛ばすので、開発のために鍵を配る必要はない。
     */
    'turnstile' => [
        'site_key' => env('CHREEID_TURNSTILE_SITE_KEY'),
        'secret_key' => env('CHREEID_TURNSTILE_SECRET_KEY'),
    ],

    /* 登録の確認メールに載せるリンクの有効分数 */
    'registration_ttl_minutes' => (int) env('CHREEID_REGISTRATION_TTL_MINUTES', 60),
];
