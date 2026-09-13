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

    /*
     * ヘッダー・ログイン画面・パンくずに出すロゴ画像の URL。
     * 空なら同梱の ChreeID ロゴ (public/icon.png) を使う。
     */
    'logo_url' => env('CHREEID_LOGO_URL'),

    /*
     * 出せる表示言語。lang/client/ と lang/server/ に同じ名前の JSON を置き、
     * ここに1行足すと選べるようになる (resources/js/lib/i18n.ts の CATALOGS にも足す)。
     *
     * 並び順がそのまま切り替えメニューの並び順になる。
     */
    'locales' => ['ja', 'en'],

    /*
     * バックアップ。
     *
     * **鍵が入っていないときは何もしない。** 中身は資格情報そのものなので、
     * 暗号化できない状態で外へ出すくらいなら取らないほうがよい。
     * 鍵は `openssl rand -base64 32` で作って .env に置く。
     *
     * 保管先は Google Drive。OAuth の refresh token を使う (サービスアカウントは
     * 自分の容量を持たず、共有ドライブを用意しないと置けないため)。4つ揃ったときだけ送る。
     */
    'backup' => [
        'key' => env('CHREEID_BACKUP_KEY'),

        /* 残す世代数。これを超えた古いものから消す */
        'keep' => (int) env('CHREEID_BACKUP_KEEP', 14),

        /*
         * サーバ内にも同じ暗号化済みの控えを置く。Drive が失敗した日にも控えを残すため。
         * 世代数ではなく日数で整理する (手で何度流しても期間で決まるように)。
         */
        'local' => [
            'enabled' => (bool) env('CHREEID_BACKUP_LOCAL', true),
            'days' => (int) env('CHREEID_BACKUP_LOCAL_DAYS', 7),
            'path' => storage_path('app/private/backups'),
        ],

        'drive' => [
            'client_id' => env('CHREEID_BACKUP_DRIVE_CLIENT_ID'),
            'client_secret' => env('CHREEID_BACKUP_DRIVE_CLIENT_SECRET'),
            'refresh_token' => env('CHREEID_BACKUP_DRIVE_REFRESH_TOKEN'),
            'folder_id' => env('CHREEID_BACKUP_DRIVE_FOLDER_ID'),
        ],
    ],

    /*
     * 管理画面に入れるアカウントのメールアドレス。カンマ区切り。
     * 検証済みのアドレスだけを見るので、未検証のまま名乗っても通らない。
     */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CHREEID_ADMIN_EMAILS', '')),
    ))),

    /*
     * 退会したアカウントを消すまでの日数。
     * 押し間違いや乗っ取りから戻せるように、すぐには消さない。
     */
    'account_purge_days' => (int) env('CHREEID_ACCOUNT_PURGE_DAYS', 31),

    /* 登録の確認メールに載せるリンクの有効分数 */
    'registration_ttl_minutes' => (int) env('CHREEID_REGISTRATION_TTL_MINUTES', 60),
];
