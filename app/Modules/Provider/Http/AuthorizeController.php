<?php
namespace App\Modules\Provider\Http;

use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Provider\Application\AmbiguousServiceAccountException;
use App\Modules\Provider\Application\IssueAuthCode;
use App\Modules\Provider\Application\SelectServiceAccount;
use App\Modules\Provider\Application\ValidateAuthorizeRequest;
use App\Modules\Provider\Infrastructure\LoginHint;
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
        private readonly SelectServiceAccount $select,
        private readonly LoginHint $loginHint,
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

        // 未ログインならログインさせ、戻ってきたら同じURLで続きから。
        // サービスが添えてきたアドレスは、二度打たせないようログイン画面まで運ぶ
        if (!$this->session->isLoggedIn()) {
            $this->loginHint->remember();

            return redirect()->guest('/login');
        }

        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect()->guest('/login');

        // 公式サービスは ChreeID の一部とみなせるので、毎回の同意を求めない
        // 同意の省略は信頼状態とは別の設定。承認済みでも省略したいサービスがある
        if (!$authorize->client->skips_consent) {
            return Inertia::render('Oauth/Consent', [
                'clientName' => $authorize->client->name,
                'clientIconUrl' => $authorize->client->icon_url,
                'scopes' => $authorize->scopes,
                'query' => $request->query(),
            ]);
        }

        return $this->redirectWithCode($authorize, $accountId, $request);
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

        return $this->redirectWithCode($authorize, $accountId, $request);
    }

    /**
     * @param AuthorizeRequest $authorize
     * @param string $accountId
     * @param Request $request
     * @return RedirectResponse|InertiaResponse
     */
    private function redirectWithCode(AuthorizeRequest $authorize, string $accountId, Request $request): RedirectResponse|InertiaResponse {
        $chosen = $request->string('service_account_id')->toString();

        try {
            $serviceAccount = $this->select->execute($authorize->client, $accountId, $chosen === '' ? null : $chosen);
        } catch (AmbiguousServiceAccountException) {
            // 統合で同じサービスに複数持っている人。黙ってどれかを選ぶと別人として入れてしまう
            return $this->chooseAccount($authorize, $accountId, $request);
        }

        $code = $this->issue->execute($authorize, $accountId, $serviceAccount->id);

        $params = ['code' => $code];
        if ($authorize->state !== null) $params['state'] = $authorize->state;

        return redirect()->away($this->withQuery($authorize->redirectUri, $params));
    }

    /**
     * どのサービスアカウントとして入るか選ばせる。
     *
     * @param AuthorizeRequest $authorize
     * @param string $accountId
     * @param Request $request
     * @return InertiaResponse
     */
    private function chooseAccount(AuthorizeRequest $authorize, string $accountId, Request $request): InertiaResponse {
        $accounts = $this->select->candidates($authorize->client, $accountId)
            ->map(fn ($account): array => [
                'id' => $account->id,
                'serviceUserId' => $account->service_user_id,
                'connectedAt' => $account->created_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('Oauth/ChooseAccount', [
            'clientName' => $authorize->client->name,
            'clientIconUrl' => $authorize->client->icon_url,
            'accounts' => $accounts,
            'query' => $request->query(),
        ]);
    }

    /**
     * redirect_uri にパラメータを足す。
     *
     * **redirect_uri は自分でクエリを持っていることがある** (`/?do=callback` など)。
     * RFC 6749 3.1.2 はそれを認めていて、パラメータはクエリ成分に追加せよとしている。
     * 無条件に `?` を足すと `?do=callback?code=...` になり、向こうで読めなくなる。
     *
     * @param string $redirectUri 検証済みのリダイレクト先
     * @param array<string, string> $params 付け足すパラメータ
     * @return string
     */
    private function withQuery(string $redirectUri, array $params): string {
        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri . $separator . http_build_query($params);
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

        return redirect()->away($this->withQuery($request->string('redirect_uri')->toString(), $params));
    }
}
