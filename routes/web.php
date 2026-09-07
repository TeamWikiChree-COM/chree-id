<?php

use App\Modules\Credential\Http\LoginController;
use App\Modules\Provider\Http\DiscoveryController;
use App\Modules\Provider\Http\JwksController;
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
