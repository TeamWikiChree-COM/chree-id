<?php
namespace App\Modules\Identity\Http;

use App\Modules\Credential\Application\ListCredentials;
use App\Modules\Identity\Application\SuggestMergeCandidates;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\ListConnectedServices;
use App\Modules\Plugin\Domain\PluginMenu;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ログイン後のトップ。今の状態を確かめるための画面
 */
class DashboardController {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ChreeSession $session,
        private readonly ListConnectedServices $services,
        private readonly SuggestMergeCandidates $candidates,
        private readonly ListCredentials $credentials,
        private readonly PluginMenu $plugins,
    ) {}

    /**
     * @return Response|RedirectResponse
     */
    public function __invoke(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $account = $this->accounts->findById($accountId);
        if ($account === null) return LoginRedirect::guest();

        $services = $this->services->execute($accountId);

        return Inertia::render('Dashboard', [
            'account' => [
                'id' => $account->id,
                'email' => $account->email,
                'displayName' => $account->displayName,
                'origin' => $account->origin->value,
                'emailVerified' => $account->isEmailVerified(),
            ],
            'credentials' => $this->credentials->execute($accountId),
            'services' => $services,
            // 連携しているサービスで使えるものだけ。関係の無いサービス向けのものまで並べない
            'plugins' => $this->plugins->itemsFor(
                PluginMenu::AREA_DASHBOARD,
                app()->getLocale(),
                array_column($services, 'clientId'),
            ),
            // 同じアドレスの別アカウント。挙げるだけで、統合は本人の操作を通す
            'mergeCandidates' => $this->candidates->execute($accountId),
        ]);
    }
}
