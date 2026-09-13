<?php

use App\Modules\ApiDocs\Http\OpenApiController;
use App\Modules\Linking\Http\ServiceAccountController;
use App\Modules\Linking\Http\ServiceAuthController;
use App\Modules\Registry\Http\EnsureProvisioningClient;
use Illuminate\Support\Facades\Route;

// サーバ間通信だけを置く。api グループはセッションも CSRF も通さない。
// 足したら ApiDocs の Paths にも書くこと (OpenApiSpecTest が突き合わせる)

Route::prefix('v1')->group(function (): void {
    // 仕様は誰でも読める。/api-docs/v1 の画面がここを読む
    Route::get('/openapi.json', OpenApiController::class);

    Route::middleware(EnsureProvisioningClient::class)->group(function (): void {
        // サービスが自分の利用者ぶんの ChreeID を扱う。利用者はサービス側の識別子で指す
        Route::prefix('/service-accounts/{serviceUserId}')
            ->where(['serviceUserId' => '[^/]+'])
            ->controller(ServiceAccountController::class)
            ->group(function (): void {
                Route::get('/', 'show');
                Route::put('/', 'update');
                Route::delete('/', 'destroy');
                Route::put('/password', 'updatePassword');
                Route::post('/claim-tickets', 'storeClaimTicket');
            });

        // サービスが自前のログインフォームのまま照合だけ任せに来る。平文が流れるので特に絞る
        Route::post('/service-auth/password', [ServiceAuthController::class, 'verifyPassword'])
            ->middleware('throttle:service-auth');

        // サービスが自前で出すメールリンクの裏付け。画面もメールも向こうのまま
        Route::post('/service-auth/magic-link', [ServiceAuthController::class, 'issueMagicLink'])
            ->middleware('throttle:service-auth');
        Route::post('/service-auth/magic-link/consume', [ServiceAuthController::class, 'consumeMagicLink'])
            ->middleware('throttle:verify');
    });
});
