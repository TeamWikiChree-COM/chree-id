<?php

use App\Modules\Audit\Http\ActivityController;
use App\Modules\Audit\Http\AdminAuditController;
use App\Modules\Credential\Http\ChallengeController;
use App\Modules\Credential\Http\LoginController;
use App\Modules\Credential\Http\MagicLinkController;
use App\Modules\Credential\Http\PasskeyController;
use App\Modules\Credential\Http\PasswordController;
use App\Modules\Credential\Http\PasswordResetController;
use App\Modules\Credential\Http\SecurityController;
use App\Modules\Device\Http\DeviceController;
use App\Modules\ExternalLogin\Http\ConnectionController;
use App\Modules\ExternalLogin\Http\ExternalLoginController;
use App\Modules\Identity\Http\DashboardController;
use App\Modules\Identity\Http\IconController;
use App\Modules\Identity\Http\MergeController;
use App\Modules\Identity\Http\ProfileController;
use App\Modules\Identity\Http\WithdrawalController;
use App\Modules\Linking\Http\ClaimController;
use App\Modules\Linking\Http\ClaimPasskeyController;
use App\Modules\Linking\Http\ConnectedServiceController;
use App\Modules\Linking\Http\ServiceAccountController;
use App\Modules\Linking\Http\ServiceAuthController;
use App\Modules\Linking\Http\SplitServiceController;
use App\Modules\Identity\Http\RegisterController;
use App\Http\Middleware\EnsureAdmin;
use App\Modules\Provider\Http\AuthorizeController;
use App\Modules\Registry\Http\AdminAccountController;
use App\Modules\Registry\Http\AdminClientController;
use App\Modules\Registry\Http\AdminController;
use App\Modules\Registry\Http\AdminLogController;
use App\Modules\Registry\Http\AdminMaintenanceController;
use App\Modules\Registry\Http\AdminMigrationController;
use App\Modules\Provider\Http\DiscoveryController;
use App\Modules\Provider\Http\JwksController;
use App\Modules\Provider\Http\TokenController;
use App\Modules\Provider\Http\UserinfoController;
use App\Http\Controllers\LocaleController;
use App\Modules\Backup\Http\AdminBackupController;
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
Route::get('/settings/connections', [ConnectionController::class, 'index']);
Route::get('/settings/devices', [DeviceController::class, 'index']);
Route::get('/settings/activity', [ActivityController::class, 'index']);

// 候補として挙がった別アカウントを寄せる。**提示と実行は別処理。**
// 実行の根拠は候補に挙がっていることではなく、相手側を証明できること
Route::get('/settings/merge/{candidate}', [MergeController::class, 'show']);
Route::post('/settings/merge', [MergeController::class, 'store'])->middleware('throttle:login');

// 取り返しがつかないので、設定画面に混ぜず専用の画面に分ける
Route::get('/settings/withdraw', [WithdrawalController::class, 'show']);
Route::post('/settings/withdraw', [WithdrawalController::class, 'store']);

// 旧パス。手元のブックマークが死なないように残す。
// Route::redirect は全メソッドに効いてしまい、下の POST /profile を飲み込むので GET だけにする
Route::get('/profile', fn () => redirect('/settings'));
Route::get('/security', fn () => redirect('/settings/security'));

// プロフィール
// 表示言語。ログインしていなくても切り替えられる (ログイン画面で要る)
Route::post('/locale', [LocaleController::class, 'update']);

Route::post('/profile', [ProfileController::class, 'update']);
Route::post('/profile/email/verify', [ProfileController::class, 'sendEmailVerification'])->middleware('throttle:register');
Route::get('/profile/email/verify/{token}', [ProfileController::class, 'confirmEmail'])->middleware('throttle:verify');
Route::post('/profile/email/change', [ProfileController::class, 'changeEmail'])->middleware('throttle:register');
Route::post('/profile/email/change/cancel', [ProfileController::class, 'cancelEmailChange']);
Route::get('/profile/email/change/{token}', [ProfileController::class, 'confirmEmailChange'])->middleware('throttle:verify');

// アイコン。表示はログインを求めない (同意画面や連携先からも引くため)
Route::get('/profile/icon/{account}', [IconController::class, 'show']);
Route::post('/profile/icon', [IconController::class, 'update']);

// 外部アカウントの後付け連携。**始めるのは POST だけ。**
// GET で始められると、細工したリンクを踏ませて第三者のアカウントを繋がせられる
// /remove を先に置く。後ろだと {provider} が "remove" を飲み込む
Route::post('/settings/connections/remove', [ConnectionController::class, 'destroy']);
Route::post('/settings/connections/{provider}', [ConnectionController::class, 'store']);

// ログイン中の端末と、2段階目を省略してよい端末。似ているが別物
Route::post('/settings/devices/sessions/revoke', [DeviceController::class, 'revokeSession']);
Route::post('/settings/devices/sessions/revoke-others', [DeviceController::class, 'revokeOtherSessions']);
Route::post('/settings/devices/trusted/revoke', [DeviceController::class, 'revokeTrusted']);
Route::post('/settings/devices/trusted/revoke-all', [DeviceController::class, 'revokeAllTrusted']);

// 連携しているサービスを利用者自身が切る。管理画面の接続サービスとは別物
Route::post('/services/{client}/revoke', [ConnectedServiceController::class, 'destroy']);

// まとめから外して単独のアカウントに戻す (統合の逆)
Route::get('/services/{serviceAccount}/split', [SplitServiceController::class, 'show']);
Route::post('/services/split', [SplitServiceController::class, 'store']);

// 認証方法の管理
Route::post('/security/totp/start', [SecurityController::class, 'startTotp']);
Route::post('/security/totp/confirm', [SecurityController::class, 'confirmTotp']);
Route::post('/security/recovery-codes', [SecurityController::class, 'generateRecoveryCodes']);
Route::post('/security/credentials/remove', [SecurityController::class, 'removeCredential']);
Route::post('/security/credentials/rename', [SecurityController::class, 'renameCredential']);
Route::post('/security/passkey/options', [PasskeyController::class, 'options']);
Route::post('/security/passkey/register', [PasskeyController::class, 'register']);
Route::post('/security/magic-link', [SecurityController::class, 'enableMagicLink']);
Route::post('/security/magic-link/disable', [SecurityController::class, 'disableMagicLink']);

// 設定画面からのパスワード変更。再設定 (/password/reset) とは裏付けが違う
Route::post('/settings/password', [PasswordController::class, 'update']);

// 裏で発行したアカウントの引き取り。サービスが渡した一度きりの URL から入る
Route::get('/claim/{token}', [ClaimController::class, 'show'])->middleware('throttle:verify');
Route::get('/claim/{token}/create', [ClaimController::class, 'create'])->middleware('throttle:verify');
Route::get('/claim/{token}/merge', [ClaimController::class, 'mergeForm'])->middleware('throttle:verify');
Route::post('/claim', [ClaimController::class, 'store'])->middleware('throttle:verify');
// 既に ChreeID を持っている人は、新しく作らずそちらへ寄せる
Route::post('/claim/merge', [ClaimController::class, 'merge'])->middleware('throttle:verify');
Route::post('/claim/passkey/options', [ClaimPasskeyController::class, 'options'])->middleware('throttle:verify');
Route::post('/claim/passkey/register', [ClaimPasskeyController::class, 'register'])->middleware('throttle:verify');

// 外部 IdP へのログイン (ChreeID が RP 側)
Route::get('/auth/{provider}/redirect', [ExternalLoginController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [ExternalLoginController::class, 'callback']);

// 分離すると1つの外部アカウントが複数の認証主体に紐付きうる。どれで入るか選ばせる
Route::get('/login/choose', [ExternalLoginController::class, 'showChoice']);
Route::post('/login/choose', [ExternalLoginController::class, 'choose'])->middleware('throttle:login');

// 管理画面。権限が無ければ 404 (そこに何かある事実も伏せる)
Route::middleware(EnsureAdmin::class)->prefix('/admin')->group(function (): void {
    Route::get('/', AdminController::class);

    Route::get('/accounts', [AdminAccountController::class, 'index']);
    Route::post('/accounts', [AdminAccountController::class, 'store']);
    Route::post('/accounts/{account}', [AdminAccountController::class, 'update']);
    // 停止 / 解除 / 退会 / 復帰 / 物理削除。何をするかは action で決まる
    Route::post('/accounts/{account}/act', [AdminAccountController::class, 'act']);

    // 本番のデプロイは artisan を走らせないので、構造の適用はここから
    Route::get('/migrations', [AdminMigrationController::class, 'index']);
    Route::post('/migrations/run', [AdminMigrationController::class, 'run']);

    // cron を組めていない間も手で流せるようにしておく
    // 全アカウントの記録が並ぶ。EnsureAdmin の内側から出さないこと
    Route::get('/audit', [AdminAuditController::class, 'index']);

    // 本番はシェルに入れない。例外の中身をここから読む
    Route::get('/logs', [AdminLogController::class, 'index']);
    Route::post('/logs/clear', [AdminLogController::class, 'clear']);

    // cron を組めていない間も、ここから手で流せるようにしておく
    Route::get('/backups', [AdminBackupController::class, 'index']);
    Route::post('/backups', [AdminBackupController::class, 'store']);

    Route::get('/maintenance', [AdminMaintenanceController::class, 'index']);
    Route::post('/maintenance/prune', [AdminMaintenanceController::class, 'prune']);

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

// サービスが自分の利用者ぶんの ChreeID を取りに来る。こちらもブラウザを介さない
Route::post('/api/v1/service-accounts', [ServiceAccountController::class, 'store']);
Route::post('/api/v1/service-accounts/claim-tickets', [ServiceAccountController::class, 'claimTicket']);
Route::post('/api/v1/service-accounts/status', [ServiceAccountController::class, 'status']);
Route::post('/api/v1/service-accounts/password', [ServiceAccountController::class, 'changePassword']);
Route::post('/api/v1/service-accounts/deactivate', [ServiceAccountController::class, 'deactivate']);

// サービスが自前のログインフォームのまま照合だけ任せに来る。平文が流れるので特に絞る
Route::post('/api/v1/service-auth/password', [ServiceAuthController::class, 'verifyPassword'])
    ->middleware('throttle:service-auth');

// サービスが自前で出すメールリンクの裏付け。画面もメールも向こうのまま
Route::post('/api/v1/service-auth/magic-link', [ServiceAuthController::class, 'issueMagicLink'])
    ->middleware('throttle:service-auth');
Route::post('/api/v1/service-auth/magic-link/consume', [ServiceAuthController::class, 'consumeMagicLink'])
    ->middleware('throttle:verify');
Route::get('/oauth/userinfo', UserinfoController::class);
