<?php

// Yahoo! JAPAN の ID 連携 (YConnect v2)。デベロッパーネットワークでアプリを登録して受け取る。
// Client ID が入ったときだけログイン画面にボタンが出る
return [
    'client_id' => env('YAHOO_JAPAN_CLIENT_ID', ''),
    // クライアントサイドで登録したアプリには無い。空なら PKCE だけで交換する
    'client_secret' => env('YAHOO_JAPAN_CLIENT_SECRET', ''),
    // 属性取得 API (UserInfo) でメールと名前を取るか。審査に通ったアプリでしか使えず、個人の登録では使えない。
    // false なら id_token の sub だけで入れる (メールの無いアカウントになる)
    'userinfo' => (bool) env('YAHOO_JAPAN_USERINFO', false),
];
