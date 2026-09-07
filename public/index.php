<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Check prerequisites for deployment
if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>【デプロイ未完了】vendor ディレクトリが見つかりません</h2>';
    echo '<p>サーバ側でまだ <code>composer install</code> が実行されていません。<br>';
    echo 'サーバに SSH ログインし、<code>composer install --no-dev</code> を実行してください。</p>';
    exit(1);
}

if (!file_exists(__DIR__.'/../.env')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>【デプロイ未完了】.env ファイルが見つかりません</h2>';
    echo '<p>サーバ側でまだ <code>.env</code> が作成されていません。<br>';
    echo 'サーバ上で <code>cp .env.example .env</code> を実行し、<code>php artisan key:generate</code> を実行してください。</p>';
    exit(1);
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
