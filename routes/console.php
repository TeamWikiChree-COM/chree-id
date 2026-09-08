<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 期限切れの登録申し込みとトークンを毎日片付ける。
// pending_registrations にはパスワードハッシュが入るので、放置しない
Schedule::command('chreeid:prune-tokens')->dailyAt('04:00');
