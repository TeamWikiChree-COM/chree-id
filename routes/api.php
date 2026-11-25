<?php

use App\Modules\ApiDocs\Http\OpenApiController;
use App\Modules\Linking\Http\ServiceAccountController;
use App\Modules\Linking\Http\ServiceAuthController;
use App\Modules\Client\Http\EnsureProvisioningClient;
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
                // サービスが自分の画面で 2FA を設定する。データはこちらに置く。コードを照合する口は回数を絞る
                Route::post('/totp/secret', 'startTotp');
                Route::put('/totp', 'enableTotp')->middleware('throttle:service-account-code');
                Route::delete('/totp', 'disableTotp')->middleware('throttle:service-account-code');
                Route::post('/recovery-codes', 'regenerateRecoveryCodes')->middleware('throttle:service-account-code');
                Route::post('/claim-tickets', 'storeClaimTicket');
            });

        // サービス側で退会した利用者を sub で畳む。OIDC でだけ結び付いた利用者は service_user_id を持たない
        Route::delete('/subjects/{sub}', [ServiceAccountController::class, 'destroyBySubject'])
            ->where(['sub' => '[^/]+']);

        // サービスが自前のログインフォームのまま照合だけ任せに来る。平文が流れるので特に絞る
        Route::post('/service-auth/password', [ServiceAuthController::class, 'verifyPassword'])
            ->middleware('throttle:service-auth');
        // パスワードの後の2要素目。合図が一度きりなので、総当たりの歯止めはパスワード側の回数制限が担う
        Route::post('/service-auth/second-factor', [ServiceAuthController::class, 'verifySecondFactor'])
            ->middleware('throttle:service-second-factor');

        // サービスが自前で出すメールリンクの裏付け。画面もメールも向こうのまま
        Route::post('/service-auth/magic-link', [ServiceAuthController::class, 'issueMagicLink'])
            ->middleware('throttle:service-auth');
        Route::post('/service-auth/magic-link/consume', [ServiceAuthController::class, 'consumeMagicLink'])
            ->middleware('throttle:verify');
    });
});
