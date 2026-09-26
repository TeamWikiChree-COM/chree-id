<?php

use Illuminate\Support\Facades\Route;
use Plugins\Template\TemplateController;

// 本体が web ミドルウェアと /plugins/<フォルダ名> の接頭辞を付けて読む。
// ここには接頭辞より後ろだけを書く。下の行は /plugins/template になる
Route::get('/', [TemplateController::class, 'index']);
