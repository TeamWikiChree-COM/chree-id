<?php

use Illuminate\Support\Facades\Route;
use Plugins\WikiHub\WikiController;

// 本体が web ミドルウェアと /plugins/wiki-hub の接頭辞を付けて読む
Route::get('/', [WikiController::class, 'index']);
