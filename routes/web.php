<?php

use App\Modules\Provider\Http\DiscoveryController;
use App\Modules\Provider\Http\JwksController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// OIDC。ディスカバリのパスは仕様で決まっているので変えないこと
Route::get('/.well-known/openid-configuration', DiscoveryController::class);
Route::get('/oauth/jwks', JwksController::class);
