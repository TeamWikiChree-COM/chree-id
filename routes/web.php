<?php

use App\Modules\Credential\Http\ChallengeController;
use App\Modules\Credential\Http\LoginController;
use App\Modules\Credential\Http\MagicLinkController;
use App\Modules\Credential\Http\PasskeyController;
use App\Modules\Credential\Http\PasswordResetController;
use App\Modules\Credential\Http\SecurityController;
use App\Modules\ExternalLogin\Http\ExternalLoginController;
use App\Modules\Identity\Http\DashboardController;
use App\Modules\Identity\Http\ProfileController;
use App\Modules\Identity\Http\RegisterController;
use App\Modules\Provider\Http\AuthorizeController;
use App\Modules\Provider\Http\DiscoveryController;
use App\Modules\Provider\Http\JwksController;
use App\Modules\Provider\Http\TokenController;
use App\Modules\Provider\Http\UserinfoController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class);

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'destroy']);

// 二要素目の入力。一次認証を通しただけの状態はここを経由しないとログインにならない
Route::get('/login/challenge', [ChallengeController::class, 'show']);
Route::post('/login/challenge', [ChallengeController::class, 'store'])->middleware('throttle:challenge');
Route::post('/login/challenge/cancel', [ChallengeController::class, 'destroy']);

// 登録はメールを確認するまでアカウントを作らない。verify がアカウント作成の実体
Route::get('/register', [RegisterController::class, 'show']);
Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
Route::get('/register/sent', [RegisterController::class, 'sent']);
Route::get('/register/verify/{token}', [RegisterController::class, 'verify'])->middleware('throttle:verify');

// メールだけでログインする経路。{token} より先に /sent を置く (でないと sent がトークン扱いになる)
Route::get('/login/magic', [MagicLinkController::class, 'show']);
Route::post('/login/magic', [MagicLinkController::class, 'store'])->middleware('throttle:register');
Route::get('/login/magic/sent', [MagicLinkController::class, 'sent']);
Route::get('/login/magic/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:verify');

// パスワード再設定。再設定してもログインはさせない (本人とは限らないため)
Route::get('/password/forgot', [PasswordResetController::class, 'show']);
Route::post('/password/forgot', [PasswordResetController::class, 'store'])->middleware('throttle:register');
Route::get('/password/forgot/sent', [PasswordResetController::class, 'sent']);
Route::get('/password/reset/{token}', [PasswordResetController::class, 'edit'])->middleware('throttle:verify');
Route::post('/password/reset', [PasswordResetController::class, 'update'])->middleware('throttle:verify');

// プロフィール
Route::get('/profile', [ProfileController::class, 'show']);
Route::post('/profile', [ProfileController::class, 'update']);

// 認証方法の管理
Route::get('/security', [SecurityController::class, 'show']);
Route::post('/security/totp/start', [SecurityController::class, 'startTotp']);
Route::post('/security/totp/confirm', [SecurityController::class, 'confirmTotp']);
Route::post('/security/recovery-codes', [SecurityController::class, 'generateRecoveryCodes']);
Route::post('/security/credentials/remove', [SecurityController::class, 'removeCredential']);
Route::post('/security/passkey/options', [PasskeyController::class, 'options']);
Route::post('/security/passkey/register', [PasskeyController::class, 'register']);
Route::post('/security/magic-link', [SecurityController::class, 'enableMagicLink']);

// 外部 IdP へのログイン (ChreeID が RP 側)
Route::get('/auth/{provider}/redirect', [ExternalLoginController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [ExternalLoginController::class, 'callback']);

// OIDC。ディスカバリのパスは仕様で決まっているので変えないこと
Route::get('/.well-known/openid-configuration', DiscoveryController::class);
Route::get('/oauth/jwks', JwksController::class);
Route::get('/oauth/authorize', AuthorizeController::class);
Route::post('/oauth/authorize/approve', [AuthorizeController::class, 'approve']);

// RP からのサーバ間通信。ブラウザのセッションを使わないので CSRF の対象外にする
Route::post('/oauth/token', TokenController::class)->withoutMiddleware([ValidateCsrfToken::class]);
Route::get('/oauth/userinfo', UserinfoController::class);
