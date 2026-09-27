<?php

use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;
use Plugins\Saml\Admin\SamlAdminController;
use Plugins\Saml\Idp\IdpController;
use Plugins\Saml\Sp\SamlController;

// 本体が web ミドルウェアと /plugins/saml の接頭辞を付けて読む。
// 外から POST されるルートはセッションを持たせたくないので、ここではなく SamlServiceProvider で登録する

// ChreeID が SP として、外部の SAML IdP でログインを受ける側
Route::get('/metadata', [SamlController::class, 'metadata']);
Route::get('/acs', [SamlController::class, 'land']);

// ChreeID が IdP として、SAML のサービスへログインさせる側
Route::get('/idp/metadata', [IdpController::class, 'metadata']);
Route::get('/idp/sso', [IdpController::class, 'sso']);
Route::get('/idp/resume', [IdpController::class, 'resume']);

// 運営の管理画面。本番はコマンドを叩けないので、鍵の作成と SP の登録をここから行う
Route::middleware(EnsureAdmin::class)->prefix('/admin')->group(function (): void {
    Route::get('/', [SamlAdminController::class, 'index']);
    Route::post('/key', [SamlAdminController::class, 'generateKey']);
    Route::post('/key/import', [SamlAdminController::class, 'importKey']);
    Route::get('/providers/create', [SamlAdminController::class, 'create']);
    Route::post('/providers', [SamlAdminController::class, 'store']);
    Route::get('/providers/{provider}/edit', [SamlAdminController::class, 'edit']);
    Route::post('/providers/{provider}', [SamlAdminController::class, 'update']);
    Route::post('/providers/{provider}/delete', [SamlAdminController::class, 'destroy']);
});
