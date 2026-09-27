<?php

// Google の OAuth クライアント。両方が揃ったときだけログイン画面にボタンが出る
return [
    'client_id' => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
];
