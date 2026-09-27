<?php
namespace App\Modules\Provider\Http;

use App\Http\Controllers\Controller;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Provider\Application\DecideSignIn;
use App\Modules\Provider\Application\PendingSignIn;
use App\Modules\Provider\Application\SignInGrant;
use App\Modules\Provider\Application\SignInStep;
use App\Modules\Provider\Domain\SignInStepKind;
use App\Modules\Provider\Infrastructure\PendingSignIns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

/**
 * プラグイン (SAML など) から頼まれたサインインを進める。
 *
 * 判断は OIDC と同じ DecideSignIn。済んだら引換券を付けてプラグインへ戻す。
 * 始め方は PluginApi::authorizeService()。
 */
class PendingSignInController extends Controller {
    private readonly PendingSignIns $pending;
    private readonly DecideSignIn $decide;
    private readonly SignInPages $pages;
    private readonly ChreeSession $session;

    public function __construct(PendingSignIns $pending, DecideSignIn $decide, SignInPages $pages, ChreeSession $session) {
        $this->pending = $pending;
        $this->decide = $decide;
        $this->pages = $pages;
        $this->session = $session;
    }

    /**
     * @param string $id 預かりの識別子
     * @return RedirectResponse|Response
     */
    public function show(string $id): RedirectResponse|Response {
        return $this->handle($id, false, null);
    }

    /**
     * 同意画面・アカウント選択画面からの送信。
     *
     * @param Request $request
     * @param string $id 預かりの識別子
     * @return RedirectResponse|Response
     */
    public function approve(Request $request, string $id): RedirectResponse|Response {
        $chosen = $request->string('service_account_id')->toString();

        return $this->handle($id, true, $chosen === '' ? null : $chosen);
    }

    /**
     * @param string $id
     * @param bool $consented
     * @param string|null $chosen 選ばれたサービスアカウント
     * @return RedirectResponse|Response
     */
    private function handle(string $id, bool $consented, ?string $chosen): RedirectResponse|Response {
        $pending = $this->pending->find($id);
        if ($pending === null) return $this->error('invalid_request', __('oauth.error.sign_in_expired'));

        // 預けてから時間が経っている間に止められていることがあるので、毎回見る
        $client = OAuthClientModel::query()->find($pending->clientId);
        if ($client === null || !$client->trust->isUsable()) return $this->error('unauthorized_client', __('oauth.error.service_suspended'));

        $step = $this->decide->execute($client, $this->session->accountId(), $consented, $chosen);
        $action = "/authorize/pending/{$id}/approve";

        return match ($step->kind) {
            SignInStepKind::LOGIN => $this->toLogin($id),
            SignInStepKind::UNCLAIMED => $this->error('access_denied', __('oauth.error.service_account_unclaimed')),
            SignInStepKind::CONSENT => $this->pages->consent($client, $pending->scopes, $action, []),
            SignInStepKind::CHOOSE_ACCOUNT => $this->pages->chooseAccount($client, $step->candidates, $action, []),
            SignInStepKind::GRANTED => $this->complete($pending, $step),
        };
    }

    /**
     * 承認の POST から来ても、ログイン後は GET の入口へ戻す。POST の URL へ戻すと開けない。
     *
     * @param string $id
     * @return RedirectResponse
     */
    private function toLogin(string $id): RedirectResponse {
        redirect()->setIntendedUrl(url("/authorize/pending/{$id}"));

        return redirect('/login');
    }

    /**
     * @param PendingSignIn $pending
     * @param SignInStep $step GRANTED の結果
     * @return RedirectResponse
     */
    private function complete(PendingSignIn $pending, SignInStep $step): RedirectResponse {
        $serviceAccount = $step->serviceAccount;
        if ($serviceAccount === null) throw new LogicException('granted step without a service account');

        $token = $this->pending->complete($pending, new SignInGrant($pending->clientId, $serviceAccount->id, $pending->scopes));
        $separator = str_contains($pending->returnUrl, '?') ? '&' : '?';

        return redirect()->to($pending->returnUrl . $separator . http_build_query(['grant' => $token]));
    }

    /**
     * 戻り先へエラーを返す方法は方式ごとに違うので、ここでは画面で伝えて止める。
     *
     * @param string $error
     * @param string $reason
     * @return Response
     */
    private function error(string $error, string $reason): Response {
        return Inertia::render('Oauth/Error', ['error' => $error, 'reason' => $reason]);
    }
}
