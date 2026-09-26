<?php

use Illuminate\Support\Facades\Route;
use Plugins\WikiHub\WikiController;

// プラグインの URL は /plugins/<name>/… にそろえる。本体の URL と衝突させないため
Route::middleware('web')->group(function (): void {
    Route::get('/plugins/wiki-hub', [WikiController::class, 'index']);
});
