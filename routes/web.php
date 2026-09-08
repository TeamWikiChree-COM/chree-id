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
use App\Http\Middleware\EnsureAdmin;
use App\Modules\Provider\Http\AuthorizeController;
use App\Modules\Registry\Http\AdminClientController;
use App\Modules\Provider\Http\DiscoveryController;
use App\Modules\Provider\Http\JwksController;
use App\Modules\Provider\Http\TokenController;
use App\Modules\Provider\Http\UserinfoController;
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
Route::post('/register/complete', [RegisterController::class, 'complete'])->middleware('throttle:verify');

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

// 設定。プロフィールとセキュリティを1か所にまとめ、画面はタブで切り替える
Route::get('/settings', [ProfileController::class, 'show']);
Route::get('/settings/security', [SecurityController::class, 'show']);

// 旧パス。手元のブックマークが死なないように残す。
// Route::redirect は全メソッドに効いてしまい、下の POST /profile を飲み込むので GET だけにする
Route::get('/profile', fn () => redirect('/settings'));
Route::get('/security', fn () => redirect('/settings/security'));

// プロフィール
Route::post('/profile', [ProfileController::class, 'update']);
Route::post('/profile/email/verify', [ProfileController::class, 'sendEmailVerification'])->middleware('throttle:register');
Route::get('/profile/email/verify/{token}', [ProfileController::class, 'confirmEmail'])->middleware('throttle:verify');
Route::post('/profile/email/change', [ProfileController::class, 'changeEmail'])->middleware('throttle:register');
Route::get('/profile/email/change/{token}', [ProfileController::class, 'confirmEmailChange'])->middleware('throttle:verify');

// 認証方法の管理
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

// 管理画面。権限が無ければ 404 (そこに何かある事実も伏せる)
Route::middleware(EnsureAdmin::class)->prefix('/admin')->group(function (): void {
    // 入口。今は接続サービスしか無いのでそこへ送る
    Route::get('/', fn () => redirect('/admin/clients'));

    Route::get('/clients', [AdminClientController::class, 'index']);
    Route::get('/clients/create', [AdminClientController::class, 'create']);
    Route::post('/clients', [AdminClientController::class, 'store']);
    Route::get('/clients/{client}/edit', [AdminClientController::class, 'edit']);
    Route::post('/clients/{client}', [AdminClientController::class, 'updateClient']);
    Route::post('/clients/{client}/secret', [AdminClientController::class, 'rotateSecret']);
    Route::post('/clients/{client}/delete', [AdminClientController::class, 'destroy']);
});

// OIDC。ディスカバリのパスは仕様で決まっているので変えないこと
Route::get('/.well-known/openid-configuration', DiscoveryController::class);
Route::get('/oauth/jwks', JwksController::class);
Route::get('/oauth/authorize', AuthorizeController::class);
Route::post('/oauth/authorize/approve', [AuthorizeController::class, 'approve']);

// RP からのサーバ間通信。CSRF の除外は bootstrap/app.php 側で指定している
Route::post('/oauth/token', TokenController::class);
Route::get('/oauth/userinfo', UserinfoController::class);
