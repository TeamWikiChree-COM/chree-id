<?php

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| ChreeID は Laravel 標準の認証を使わない。
|
| 標準の認証は users テーブルと Authenticatable なモデルを前提にしているが、
| こちらのアカウントは auth_identities で、ログイン状態は ChreeSession が
| セッションに置くアカウントIDだけで表す。認証手段 (パスワード / パスキー /
| TOTP / マジックリンク) も credentials に自前で持っている。
|
| そのため guard も provider も定義しない。雛形のまま残しておくと、
| 実在しない users テーブルとモデルを指し続けることになる。
|
| ここを消してしまうと config('auth.defaults') を読むフレームワーク側が
| null を掴むので、既定値の形だけは残す。
|
*/

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [],

    'providers' => [],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
