<?php
namespace App\Modules\ExternalLogin\Http;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Domain\LoginMethod;
use App\Modules\ExternalLogin\Domain\CodeExchangeIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\ExternalLogin\Infrastructure\ExternalLoginFlow;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 外部 IdP へのログイン (ChreeID が RP 側)
 *
 * 設定画面から後付けで連携する入口は ConnectionController。認可コードで戻る IdP の着地は
 * どちらもここに来る。戻ってきたあとの処理は ExternalLoginLanding。
 */
class ExternalLoginController extends Controller {
    public function __construct(
        private readonly ExternalIdpRegistry $registry,
        private readonly ExternalLoginLanding $landing,
        private readonly AuthIdentityRepository $accounts,
        private readonly ExternalLoginFlow $flow,
    ) {}

    /**
     * @param Request $request
     * @param string $provider プロバイダ名
     * @return RedirectResponse
     */
    public function redirect(Request $request, string $provider): RedirectResponse {
        $idp = $this->registry->get($provider);
        if ($idp === null) return redirect('/login')->withErrors(['email' => __('auth.external_login.provider_unsupported')]);

        return redirect()->away($this->flow->start($idp, $request->string('claim_token')->toString()));
    }

    /**
     * 認可コードで戻ってくる IdP の着地。
     *
     * @param Request $request
     * @param string $provider プロバイダ名
     * @return RedirectResponse
     */
    public function callback(Request $request, string $provider): RedirectResponse {
        $idp = $this->registry->get($provider);

        // SAML などは別の受け口で受ける。ここで受けると state だけ消費して失敗する
        if (!$idp instanceof CodeExchangeIdp) return $this->landing->fail(__('auth.external_login.provider_unsupported'));

        $code = $request->string('code')->toString();
        if ($code === '') return $this->landing->abort(__('auth.external_login.canceled'));

        return $this->landing->finish(
            $provider,
            $request->string('state')->toString(),
            static fn (string $nonce): ExternalIdentity => $idp->exchange($code, $nonce),
        );
    }

    /**
     * 選ばれた認証主体で続ける。
     *
     * **画面から戻ってきたIDは信用しない。** セッションに置いた候補に無ければ通さない。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function choose(Request $request): RedirectResponse {
        $candidates = $this->flow->candidates();
        $chosen = $request->string('account_id')->toString();
        if (!\in_array($chosen, $candidates, true)) return $this->landing->fail(__('auth.external_login.link_failed'));

        $this->flow->forgetCandidates();

        return $this->landing->completeLogin($chosen, LoginMethod::OAUTH->value, $this->flow->pullClaimToken());
    }

    /**
     * 選択画面。候補が無ければログインへ戻す。
     *
     * @return Response|RedirectResponse
     */
    public function showChoice(): Response|RedirectResponse {
        $candidates = $this->flow->candidates();
        if ($candidates === []) return redirect('/login');

        $accounts = [];
        foreach ($candidates as $id) {
            $account = $this->accounts->findById($id);
            if ($account === null || $account->isSuspended()) continue;

            $accounts[] = [
                'id' => $account->id,
                'email' => $account->email,
                'displayName' => $account->displayName,
            ];
        }

        return Inertia::render('Auth/ChooseIdentity', ['accounts' => $accounts]);
    }
}
