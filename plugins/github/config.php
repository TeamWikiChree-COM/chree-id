<?php

// GitHub の OAuth クライアント。両方が揃ったときだけログイン画面にボタンが出る
return [
    'client_id' => env('GITHUB_CLIENT_ID', ''),
    'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
];
