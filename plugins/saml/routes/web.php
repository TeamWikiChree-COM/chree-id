<?php

use Illuminate\Support\Facades\Route;
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
