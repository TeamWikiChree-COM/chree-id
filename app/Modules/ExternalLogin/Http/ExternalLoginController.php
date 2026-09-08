<?php
namespace App\Modules\ExternalLogin\Http;

use App\Modules\ExternalLogin\Application\LinkExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * 外部 IdP へのログイン (ChreeID が RP 側)
 */
class ExternalLoginController {
    private const STATE_KEY = 'external_login.state';
    private const NONCE_KEY = 'external_login.nonce';

    public function __construct(
        private readonly ExternalIdpRegistry $registry,
        private readonly LinkExternalIdentity $link,
        private readonly ChreeSession $session,
    ) {}

    /**
     * @param Request $request
     * @param string $provider プロバイダ名
     * @return RedirectResponse
     */
    public function redirect(Request $request, string $provider): RedirectResponse {
        $idp = $this->registry->get($provider);
        if ($idp === null) return redirect('/login')->withErrors(['email' => '対応していない連携先です']);

        $state = Str::random(40);
        $nonce = Str::random(40);

        $request->session()->put(self::STATE_KEY, $state);
        $request->session()->put(self::NONCE_KEY, $nonce);

        return redirect()->away($idp->authorizationUrl($state, $nonce));
    }

    /**
     * @param Request $request
     * @param string $provider プロバイダ名
     * @return RedirectResponse
     */
    public function callback(Request $request, string $provider): RedirectResponse {
        $idp = $this->registry->get($provider);
        if ($idp === null) return $this->fail('対応していない連携先です');

        $state = $request->session()->pull(self::STATE_KEY);
        $nonce = $request->session()->pull(self::NONCE_KEY);

        // state が一致しないリクエストは、第三者に開始させられた可能性がある
        if (!\is_string($state) || !hash_equals($state, $request->string('state')->toString())) {
            return $this->fail('連携を確認できませんでした');
        }
        if (!\is_string($nonce)) return $this->fail('連携を確認できませんでした');

        $code = $request->string('code')->toString();
        if ($code === '') return $this->fail('連携がキャンセルされました');

        try {
            $identity = $idp->exchange($code, $nonce);
            $accountId = $this->link->execute($identity);
        } catch (ExternalIdentityConflict) {
            return $this->fail('既存のアカウントでログインしてから連携してください');
        } catch (Throwable) {
            return $this->fail('連携に失敗しました');
        }

        $this->session->login($accountId);

        return redirect('/');
    }

    /**
     * @param string $message 画面に出す文言
     * @return RedirectResponse
     */
    private function fail(string $message): RedirectResponse {
        return redirect('/login')->withErrors(['email' => $message]);
    }
}
