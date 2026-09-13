<?php

use App\Http\Middleware\DetectSessionLoss;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginSession;
use App\Support\Api\ApiError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
            DetectSessionLoss::class,
        ]);

        // エラーの説明文を呼び出し元の Accept-Language に合わせる。セッションが無くても動く
        $middleware->api(append: [SetLocale::class]);

        // RP からのサーバ間通信。ブラウザのセッションを使わないので CSRF の対象外にする。
        // ルート側の withoutMiddleware() では除外されないため、ここで指定する。
        // /api/* は api グループで、そもそも CSRF を通らない
        $middleware->preventRequestForgery(except: [
            'oauth/token',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 入力エラーも他の失敗と同じ error / error_description の形で返す。
        // 呼び出し元がエラーの読み口を2つ持たずに済むように
        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? ApiError::invalidRequest($e)
            : null);
    })->create();

// 翻訳の正は lang/*.json で、読むのはそこから生成した PHP (ARCHITECTURE.md 10章)。
// 生成物は履歴に入れず CI が作るので、既定の lang/ とは別の場所を見せる
$app->useLangPath(dirname(__DIR__) . '/generated/lang');

return $app;
