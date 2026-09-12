<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\CompleteAuthentication;
use App\Modules\Credential\Application\ConsumeMagicLink;
use App\Modules\Credential\Application\RequestMagicLink;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\PendingAuthentication;
use App\Modules\Device\Application\TrustedDevices;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Support\Turnstile\TurnstileGuard;
use App\Modules\Provider\Infrastructure\LoginHint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * メールだけでログインする経路。
 *
 * 送信の応答はアドレスの登録有無で変わらないので、存在確認には使えない。
 * リンクは一要素でしかないので、2FA を設定していれば二要素目の入力に進む。
 */
class MagicLinkController {
    public function __construct(
        private readonly RequestMagicLink $requestLink,
        private readonly ConsumeMagicLink $consume,
        private readonly CompleteAuthentication $complete,
        private readonly PendingAuthentication $pending,
        private readonly ChreeSession $session,
        private readonly LoginHint $loginHint,
        private readonly TrustedDevices $trustedDevices,
    ) {}

    /**
     * @return Response
     */
    public function show(): Response {
        return Inertia::render('Auth/MagicLink', ['email' => $this->loginHint->pull()]);
    }

    /**
     * @param Request $request
     * @param TurnstileGuard $turnstile
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request, TurnstileGuard $turnstile): RedirectResponse {
        $turnstile->check($request);

        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $email = $request->string('email')->toString();
        $this->requestLink->execute($email);

        return redirect('/login/magic/sent')->with('magicLinkEmail', $email);
    }

    /**
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function sent(Request $request): Response|RedirectResponse {
        $email = $request->session()->get('magicLinkEmail');
        if (!is_string($email)) return redirect('/login/magic');

        return Inertia::render('Auth/MagicLinkSent', ['email' => $email]);
    }

    /**
     * @param Request $request
     * @param string $token メールに載せた平文トークン
     * @return Response|RedirectResponse
     */
    public function consume(Request $request, string $token): Response|RedirectResponse {
        $factors = new VerifiedFactors();
        $accountId = $this->consume->execute($token, $factors);

        if ($accountId === null) return Inertia::render('Auth/MagicLinkFailed');

        // メールを開けただけでは2要素にならないので、2FA があれば足りない。
        // 本人が2段階目を通して信頼した端末でだけ、そこを省く
        $trusted = $this->trustedDevices->isTrusted($accountId, $request->cookie(TrustedDevices::COOKIE));

        if (!$trusted && !$this->complete->execute($accountId, $factors)) {
            $this->pending->start($accountId, $factors);

            return redirect('/login/challenge');
        }

        $this->session->login($accountId);

        return redirect()->intended('/');
    }
}
