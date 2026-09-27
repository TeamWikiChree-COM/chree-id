<?php

use Illuminate\Support\Facades\Route;
use Plugins\Saml\SamlController;

// 本体が web ミドルウェアと /plugins/saml の接頭辞を付けて読む。
// POST /acs はセッションを持たせたくないので、ここではなく SamlServiceProvider で登録する
Route::get('/metadata', [SamlController::class, 'metadata']);
Route::get('/acs', [SamlController::class, 'land']);
