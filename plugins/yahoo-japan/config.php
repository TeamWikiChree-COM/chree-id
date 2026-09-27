<?php

// Yahoo! JAPAN の ID 連携 (YConnect v2)。デベロッパーネットワークでアプリを登録して受け取る。
// 両方が揃ったときだけログイン画面にボタンが出る
return [
    'client_id' => env('YAHOO_JAPAN_CLIENT_ID', ''),
    'client_secret' => env('YAHOO_JAPAN_CLIENT_SECRET', ''),
];
