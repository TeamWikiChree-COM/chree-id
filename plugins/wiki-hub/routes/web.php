<?php

use Illuminate\Support\Facades\Route;
use Plugins\WikiHub\WikiController;

// 連携サービスにある自分のウィキを一元で表示する画面
Route::get('/', [WikiController::class, 'index']);
