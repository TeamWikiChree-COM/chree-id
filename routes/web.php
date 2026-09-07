<?php

use App\Modules\Credential\Http\LoginController;
use App\Modules\Provider\Http\AuthorizeController;
use App\Modules\Provider\Http\DiscoveryController;
use App\Modules\Provider\Http\JwksController;
use App\Modules\Provider\Http\TokenController;
use App\Modules\Provider\Http\UserinfoController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', ['issuer' => config('chreeid.issuer')]);
});

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy']);

// OIDC。ディスカバリのパスは仕様で決まっているので変えないこと
Route::get('/.well-known/openid-configuration', DiscoveryController::class);
Route::get('/oauth/jwks', JwksController::class);
Route::get('/oauth/authorize', AuthorizeController::class);
Route::post('/oauth/authorize/approve', [AuthorizeController::class, 'approve']);

// RP からのサーバ間通信。ブラウザのセッションを使わないので CSRF の対象外にする
Route::post('/oauth/token', TokenController::class)->withoutMiddleware([ValidateCsrfToken::class]);
Route::get('/oauth/userinfo', UserinfoController::class);
