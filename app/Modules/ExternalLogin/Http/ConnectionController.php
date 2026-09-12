<?php
namespace App\Modules\ExternalLogin\Http;

use App\Modules\Credential\Application\RemoveCredential;
use App\Modules\ExternalLogin\Application\ConnectedExternalAccounts;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\ExternalLogin\Infrastructure\ExternalLoginFlow;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 設定画面からの外部アカウント連携。
 *
 * ログインのついでではなく、後から足す経路。**始めるのは必ず POST。**
 * GET で始められると、細工したリンクを踏ませて第三者のアカウントを
 * 本人のものとして繋がせられる。
 */
class ConnectionController {


    private readonly ChreeSession $session;
    private readonly ExternalIdpRegistry $registry;
    private readonly ExternalLoginFlow $flow;
    private readonly ConnectedExternalAccounts $connected;
    private readonly RemoveCredential $remove;

    public function __construct(ChreeSession $session, ExternalIdpRegistry $registry, ExternalLoginFlow $flow, ConnectedExternalAccounts $connected, RemoveCredential $remove) {
        $this->session = $session;
        $this->registry = $registry;
        $this->flow = $flow;
        $this->connected = $connected;
        $this->remove = $remove;
    }

    /**
     * @return Response|RedirectResponse
     */
    public function index(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        return Inertia::render('Settings/Connections', [
            'connections' => $this->connected->listFor($accountId),
            'providers' => $this->registry->usableNames(),
        ]);
    }

    /**
     * 連携を始める。IdP から戻ってきた先の処理は ExternalLoginController。
     *
     * **外部へ送り出すのは Inertia::location。** 素の 302 だと XHR がそのまま追いかけ、
     * IdP の HTML を Inertia の応答として受け取ってしまう。
     *
     * @param Request $request
     * @param string $provider プロバイダ名
     * @return SymfonyResponse
     */
    public function store(Request $request, string $provider): SymfonyResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $idp = $this->registry->get($provider);
        if ($idp === null || !$idp->isConfigured()) {
            return redirect('/settings/connections')->withErrors(['provider' => '対応していない連携先です']);
        }

        return Inertia::location($this->flow->start($idp, linkAccountId: $accountId));
    }

    /**
     * 連携を切る。
     *
     * 最後の認証手段だった場合は切らせない (RemoveCredential が弾く)。
     * 切れてしまうと、外部アカウントでしか入れない人がログインできなくなる。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function destroy(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate(['id' => ['required', 'string']]);

        try {
            $this->remove->executeById($accountId, $request->string('id')->toString());
        } catch (RuntimeException $e) {
            return redirect('/settings/connections')->withErrors(['provider' => $e->getMessage()]);
        }

        return redirect('/settings/connections')->with('connectionRemoved', true);
    }
}
