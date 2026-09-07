<?php
namespace App\Modules\Provider\Http;

use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Provider\Application\IssueAuthCode;
use App\Modules\Provider\Application\ValidateAuthorizeRequest;
use App\Modules\Provider\Domain\AuthorizeError;
use App\Modules\Provider\Domain\AuthorizeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * OIDC の認可エンドポイント。
 *
 * ここは画面ではなくプロトコルなので、Inertia を通さず直接リダイレクトを返す。
 */
class AuthorizeController {
    public function __construct(
        private readonly ValidateAuthorizeRequest $validate,
        private readonly IssueAuthCode $issue,
        private readonly ChreeSession $session,
    ) {}

    /**
     * @param Request $request
     * @return RedirectResponse|InertiaResponse
     */
    public function __invoke(Request $request): RedirectResponse|InertiaResponse {
        try {
            $authorize = $this->validate->execute($request);
        } catch (AuthorizeError $error) {
            return $this->handleError($request, $error);
        }

        // 未ログインならログインさせ、戻ってきたら同じURLで続きから
        if (!$this->session->isLoggedIn()) return redirect()->guest('/login');

        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect()->guest('/login');

        // 公式サービスは ChreeID の一部とみなせるので、毎回の同意を求めない
        if (!$authorize->client->trust->skipsConsent()) {
            return Inertia::render('Oauth/Consent', [
                'clientName' => $authorize->client->name,
                'scopes' => $authorize->scopes,
                'query' => $request->query(),
            ]);
        }

        return $this->redirectWithCode($authorize, $accountId);
    }

    /**
     * 同意画面で許可されたとき。
     *
     * 画面から戻ってきたパラメータを信用せず、もう一度検証してからコードを出す。
     *
     * @param Request $request
     * @return RedirectResponse|InertiaResponse
     */
    public function approve(Request $request): RedirectResponse|InertiaResponse {
        try {
            $authorize = $this->validate->execute($request);
        } catch (AuthorizeError $error) {
            return $this->handleError($request, $error);
        }

        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect()->guest('/login');

        return $this->redirectWithCode($authorize, $accountId);
    }

    /**
     * @param AuthorizeRequest $authorize
     * @param string $accountId
     * @return RedirectResponse
     */
    private function redirectWithCode(AuthorizeRequest $authorize, string $accountId): RedirectResponse {
        $code = $this->issue->execute($authorize, $accountId);

        $params = ['code' => $code];
        if ($authorize->state !== null) $params['state'] = $authorize->state;

        return redirect()->away($authorize->redirectUri . '?' . http_build_query($params));
    }

    /**
     * redirect_uri を確認できていないエラーは RP へ返さない。
     * 未確認のURLへ飛ばすとオープンリダイレクタになる。
     *
     * @param Request $request
     * @param AuthorizeError $error
     * @return RedirectResponse|InertiaResponse
     */
    private function handleError(Request $request, AuthorizeError $error): RedirectResponse|InertiaResponse {
        if (!$error->canRedirect) {
            return Inertia::render('Oauth/Error', [
                'error' => $error->error,
                'reason' => $error->reason,
            ]);
        }

        $params = ['error' => $error->error, 'error_description' => $error->reason];
        $state = $request->string('state')->toString();
        if ($state !== '') $params['state'] = $state;

        return redirect()->away($request->string('redirect_uri')->toString() . '?' . http_build_query($params));
    }
}
