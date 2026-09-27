<?php
namespace App\Modules\Provider\Http;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Provider\Application\AuthorizeRequest;
use App\Modules\Provider\Application\DecideSignIn;
use App\Modules\Provider\Application\IssueAuthCode;
use App\Modules\Provider\Application\LoginHint;
use App\Modules\Provider\Application\SignInStep;
use App\Modules\Provider\Application\ValidateAuthorizeRequest;
use App\Modules\Provider\Domain\AuthorizeError;
use App\Modules\Provider\Domain\SignInStepKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use LogicException;

/**
 * OIDC の認可エンドポイント。
 *
 * ここは画面ではなくプロトコルなので、Inertia を通さず直接リダイレクトを返す。
 * 次に何をするかの判断は DecideSignIn。プラグインから頼まれたサインインと共通。
 */
class AuthorizeController extends Controller {
    private const APPROVE_ACTION = '/oauth/authorize/approve';

    private readonly ValidateAuthorizeRequest $validate;
    private readonly IssueAuthCode $issue;
    private readonly ChreeSession $session;
    private readonly DecideSignIn $decide;
    private readonly SignInPages $pages;
    private readonly LoginHint $loginHint;

    public function __construct(ValidateAuthorizeRequest $validate, IssueAuthCode $issue, ChreeSession $session, DecideSignIn $decide, SignInPages $pages, LoginHint $loginHint) {
        $this->validate = $validate;
        $this->issue = $issue;
        $this->session = $session;
        $this->decide = $decide;
        $this->pages = $pages;
        $this->loginHint = $loginHint;
    }

    /**
     * @param Request $request
     * @return RedirectResponse|InertiaResponse
     */
    public function __invoke(Request $request): RedirectResponse|InertiaResponse {
        return $this->handle($request, false);
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
        return $this->handle($request, true);
    }

    /**
     * @param Request $request
     * @param bool $consented 同意画面で許可された後か
     * @return RedirectResponse|InertiaResponse
     */
    private function handle(Request $request, bool $consented): RedirectResponse|InertiaResponse {
        try {
            $authorize = $this->validate->execute($request);
        } catch (AuthorizeError $error) {
            return $this->handleError($request, $error);
        }

        $chosen = $request->string('service_account_id')->toString();
        $step = $this->decide->execute($authorize->client, $this->session->accountId(), $consented, $chosen === '' ? null : $chosen);

        return match ($step->kind) {
            SignInStepKind::LOGIN => $this->toLogin(),
            SignInStepKind::UNCLAIMED => $this->rejectUnclaimed($request),
            SignInStepKind::CONSENT => $this->pages->consent($authorize->client, $authorize->scopes, self::APPROVE_ACTION, $request->query()),
            SignInStepKind::CHOOSE_ACCOUNT => $this->pages->chooseAccount($authorize->client, $step->candidates, self::APPROVE_ACTION, $request->query()),
            SignInStepKind::GRANTED => $this->redirectWithCode($authorize, $step),
        };
    }

    /**
     * ログインから戻ってきたら同じURLで続きから。
     * サービスが添えてきたアドレスは、二度打たせないようログイン画面まで運ぶ。
     *
     * @return RedirectResponse
     */
    private function toLogin(): RedirectResponse {
        $this->loginHint->remember();

        return redirect()->guest('/login');
    }

    /**
     * @param AuthorizeRequest $authorize
     * @param SignInStep $step GRANTED の結果
     * @return RedirectResponse
     */
    private function redirectWithCode(AuthorizeRequest $authorize, SignInStep $step): RedirectResponse {
        $serviceAccount = $step->serviceAccount;
        if ($serviceAccount === null) throw new LogicException('granted step without a service account');

        $code = $this->issue->execute($authorize, $serviceAccount->auth_identity_id, $serviceAccount->id);

        $params = ['code' => $code];
        if ($authorize->state !== null) $params['state'] = $authorize->state;

        return redirect()->away($this->withQuery($authorize->redirectUri, $params));
    }

    /**
     * 引き取り前のサービスアカウントには、発行元で移行してもらうよう画面で伝える。
     *
     * RP へエラーで返すと、本人には何をすればよいか伝わらない。
     *
     * @param Request $request
     * @return RedirectResponse|InertiaResponse
     */
    private function rejectUnclaimed(Request $request): RedirectResponse|InertiaResponse {
        return $this->handleError($request, AuthorizeError::fatal('access_denied', __('oauth.error.service_account_unclaimed')));
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
