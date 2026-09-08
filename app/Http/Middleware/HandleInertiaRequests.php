<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Registry\Domain\AdminAccess;
use App\Support\Turnstile\TurnstileVerifier;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return bool ログイン中のアカウントが管理者か
     */
    private function isAdmin(): bool {
        $accountId = app(ChreeSession::class)->accountId();
        if ($accountId === null) return false;

        return app(AdminAccess::class)->allows(app(ChreeAccountRepository::class)->findById($accountId));
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // 復旧コードの平文は発行直後の1回しか出せないので、フラッシュで渡す
            'flash' => [
                'recoveryCodes' => $request->session()->get('recoveryCodes'),
                'passwordReset' => $request->session()->get('passwordReset'),
                'profileSaved' => $request->session()->get('profileSaved'),
            ],

            // 未設定なら null。フォーム側はこれを見てウィジェットを出すかどうか決める
            'turnstileSiteKey' => app(TurnstileVerifier::class)->siteKey(),

            // 管理画面への導線を出すかどうか。権限そのものはミドルウェアが見る
            'isAdmin' => $this->isAdmin(),
        ];
    }
}
