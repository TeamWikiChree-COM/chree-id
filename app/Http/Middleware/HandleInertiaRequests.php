<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Application\AccountIcons;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\Admin\Domain\AdminAccess;
use App\Support\Locale\Locales;
use App\Support\Turnstile\TurnstileVerifier;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware {
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
    public function version(Request $request): ?string {
        return parent::version($request);
    }

    /**
     * フラッシュで渡すキー。画面側の型 (resources/js/types) と揃えること。
     */
    private const FLASH_KEYS = [
        'recoveryCodes',
        'passwordReset',
        'profileSaved',
        'verificationSent',
        'emailVerified',
        'emailChangeSent',
        'emailChangeCancelled',
        'emailChanged',
        'accountEmail',
        'serviceEmailSaved',
        'serviceRevoked',
        'accountMerged',
        'serviceSaved',
        'reviewRequested',
        'issuedSecret',
        'clientApproved',
        'migrationOutput',
        'prunedTokens',
        'logCleared',
        'accountCreated',
        'passwordChanged',
        'iconSaved',
        'connectionAdded',
        'connectionRemoved',
        'sessionsRevoked',
        'trustRevoked',
        'backupTaken',
    ];

    /**
     * ログイン中の本人のアイコン。
     *
     * @return string|null 未設定、または未ログインなら null
     */
    private function iconUrl(): ?string {
        $accountId = app(ChreeSession::class)->accountId();
        if ($accountId === null) return null;

        $account = app(AuthIdentityRepository::class)->findById($accountId);

        return $account === null ? null : app(AccountIcons::class)->urlFor($account);
    }

    /**
     * @return bool ログイン中のアカウントが管理者か
     */
    private function isAdmin(): bool {
        $accountId = app(ChreeSession::class)->accountId();
        if ($accountId === null) return false;

        return app(AdminAccess::class)->allows(app(AuthIdentityRepository::class)->findById($accountId));
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array {
        return [
            ...parent::share($request),

            'flash' => $this->flash($request),

            // 未設定なら null。フォーム側はこれを見てウィジェットを出すかどうか決める
            'turnstileSiteKey' => app(TurnstileVerifier::class)->siteKey(),

            // 管理画面への導線を出すかどうか。権限そのものはミドルウェアが見る
            'isAdmin' => $this->isAdmin(),

            // 設定が揃っている外部 IdP だけ。押しても何も起きないボタンを出さないため
            'externalIdps' => app(ExternalIdpRegistry::class)->usableNames(),

            // ヘッダーの中身を切り替えるためだけの値。認可には使わない
            'isLoggedIn' => app(ChreeSession::class)->isLoggedIn(),

            // ヘッダーに出すアイコン。未設定なら null
            'iconUrl' => $this->iconUrl(),

            // ヘッダー・ログイン画面・パンくずに出すアプリ名とロゴ。.env で差し替え可能
            'appName' => config('app.name'),
            'appLogoUrl' => config('chreeid.logo_url') ?: '/icon.png',

            // 辞書は Vite がバンドルへ畳み込んでいるので、渡すのは名前だけ。
            // 辞書ごと載せると、ページ遷移のたびに全文がレスポンスに乗る
            'locale' => app()->getLocale(),

            // 切り替えメニューに並べる分。config/chreeid.php の locales がそのまま来る
            'locales' => app(Locales::class)->available(),
        ];
    }

    /**
     * 復旧コードの平文は発行直後の1回しか出せないので、フラッシュで渡す。
     *
     * @return array<string, mixed>
     */
    private function flash(Request $request): array {
        $session = $request->session();

        return array_combine(self::FLASH_KEYS, array_map(fn (string $key): mixed => $session->get($key), self::FLASH_KEYS));
    }
}
