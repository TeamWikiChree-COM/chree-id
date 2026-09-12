<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // 表示言語を最初に決める。あとにすると共有 props もバリデーションの文言も、
            // 決まる前の言語で組み立ってしまう。
            // **prepend にはしない。** StartSession より前に出てしまい、
            // ログイン中の選択 (認証主体の行) を読むためのセッションがまだ無い
            SetLocale::class,

            HandleInertiaRequests::class,
            TrackLoginSession::class,
        ]);

        // RP からのサーバ間通信。ブラウザのセッションを使わないので CSRF の対象外にする。
        // ルート側の withoutMiddleware() では除外されないため、ここで指定する
        $middleware->validateCsrfTokens(except: [
            'oauth/token',
            'api/v1/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// 翻訳の正は lang/*.json で、読むのはそこから生成した PHP (ARCHITECTURE.md 10章)。
// 生成物は履歴に入れず CI が作るので、既定の lang/ とは別の場所を見せる
$app->useLangPath(dirname(__DIR__) . '/generated/lang');

return $app;
