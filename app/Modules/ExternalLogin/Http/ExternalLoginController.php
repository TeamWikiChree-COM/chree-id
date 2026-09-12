<?php
namespace App\Modules\ExternalLogin\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Audit\Domain\LoginMethod;
use App\Modules\ExternalLogin\Application\LinkExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\ExternalLogin\Infrastructure\ExternalLoginFlow;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\ClaimServiceAccount;
use App\Modules\Linking\Application\ClaimTickets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * 外部 IdP へのログイン (ChreeID が RP 側)
 *
 * 設定画面から後付けで連携する入口は ConnectionController。IdP からの着地は
 * どちらもここに来る (1か所にまとめないと state の扱いが分かれる)。
 */
class ExternalLoginController {
    public function __construct(
        private readonly ExternalIdpRegistry $registry,
        private readonly LinkExternalIdentity $link,
        private readonly ChreeSession $session,
        private readonly ClaimTickets $tickets,
        private readonly ClaimServiceAccount $claim,
        private readonly AuthIdentityRepository $accounts,
        private readonly ExternalLoginFlow $flow,
        private readonly AuditLog $audit,
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
     * @param Request $request
     * @param string $provider プロバイダ名
     * @return RedirectResponse
     */
    public function callback(Request $request, string $provider): RedirectResponse {
        $idp = $this->registry->get($provider);
        if ($idp === null) return $this->fail(__('auth.external_login.provider_unsupported'));

        $linkAccountId = $this->flow->pullLinkAccountId();
        $nonce = $this->flow->pullNonce();
        $claimToken = $this->flow->pullClaimToken();

        if (!$this->flow->matchesState($request->string('state')->toString()) || $nonce === null) {
            return $this->fail(__('auth.external_login.link_failed'), $linkAccountId);
        }

        $code = $request->string('code')->toString();
        if ($code === '') return $this->fail(__('auth.external_login.canceled'), $linkAccountId);

        try {
            $identity = $idp->exchange($code, $nonce);
        } catch (Throwable) {
            return $this->fail(__('auth.external_login.failed'), $linkAccountId);
        }

        // 設定画面から始めた連携は、ログインではなく本人のアカウントへ足すだけ
        if ($linkAccountId !== null) return $this->addToAccount($linkAccountId, $identity);

        return $this->loginWith($identity, $claimToken);
    }

    /**
     * ログイン経路。連携先のアカウントを決めてセッションを張る。
     *
     * @param ExternalIdentity $identity IdP が主張してきた内容
     * @param string|null $claimToken 引き取り中ならそのトークン
     * @return RedirectResponse
     */
    private function loginWith(ExternalIdentity $identity, ?string $claimToken): RedirectResponse {
        try {
            // 分離すると、同じ外部アカウントが複数の認証主体に紐付きうる。
            // 黙ってどれかを選ぶと別のアカウントとして入れてしまうので、本人に選ばせる
            $candidates = $this->link->candidates($identity);
            if (count($candidates) > 1) {
                $this->flow->keepCandidates($candidates, $claimToken);

                return redirect('/login/choose');
            }

            $accountId = $this->link->execute($identity);
        } catch (ExternalIdentityConflict) {
            return $this->fail(__('auth.external_login.link_existing_account_first'));
        } catch (Throwable) {
            return $this->fail(__('auth.external_login.failed'));
        }

        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->isSuspended()) return $this->fail(__('auth.external_login.account_unusable'));

        if ($claimToken !== null) $this->finalizeClaim($claimToken, $accountId);

        $this->session->login($accountId, LoginMethod::OAUTH->with($identity->provider));

        return redirect('/');
    }

    /**
     * 設定画面から始めた連携の着地。
     *
     * **セッションの本人と、連携を始めた本人が一致することを確かめる。**
     * 途中で別のアカウントに入り直していた場合、そちらに足すと本人の意図とずれる。
     *
     * @param string $linkAccountId 連携を始めたときのアカウントID
     * @param ExternalIdentity $identity IdP が主張してきた内容
     * @return RedirectResponse
     */
    private function addToAccount(string $linkAccountId, ExternalIdentity $identity): RedirectResponse {
        if ($this->session->accountId() !== $linkAccountId) {
            return $this->fail(__('auth.external_login.link_failed'));
        }

        try {
            $this->link->linkTo($linkAccountId, $identity);
        } catch (ExternalIdentityConflict $e) {
            return redirect('/settings/connections')->withErrors(['provider' => $e->getMessage()]);
        } catch (Throwable) {
            return redirect('/settings/connections')->withErrors(['provider' => __('auth.external_login.failed')]);
        }

        $this->audit->record(AuditAction::CONNECTION_ADDED, $linkAccountId, ['provider' => $identity->provider]);

        return redirect('/settings/connections')->with('connectionAdded', true);
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
        if ($candidates === []) return $this->fail(__('auth.external_login.link_failed'));

        $chosen = $request->string('account_id')->toString();
        if (!\in_array($chosen, $candidates, true)) return $this->fail(__('auth.external_login.link_failed'));

        $account = $this->accounts->findById($chosen);
        if ($account === null || $account->isSuspended()) return $this->fail(__('auth.external_login.account_unusable'));

        $this->flow->forgetCandidates();
        $claimToken = $this->flow->pullClaimToken();

        if ($claimToken !== null) $this->finalizeClaim($claimToken, $chosen);

        $this->session->login($chosen, LoginMethod::OAUTH->value);

        return redirect('/');
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

    /**
     * 引き取り中だった場合、外部IdPの連携そのものを認証手段として引き取りを完了する。
     *
     * 連携で解決したアカウントが引き取り対象と別人のものだった場合は、
     * 引き取りには繋げず通常ログインとして扱う (メールが一致しなかった等)。
     *
     * @param string $claimToken
     * @param string $accountId 連携で解決したアカウントID
     * @return void
     */
    private function finalizeClaim(string $claimToken, string $accountId): void {
        $link = $this->tickets->find($claimToken);
        if ($link === null || $link->isClaimed() || $link->auth_identity_id !== $accountId) return;

        try {
            $this->claim->executeWithExistingCredential($link);
        } catch (Throwable) {
            // 引き取りが不成立でも、連携自体は済んでいるのでログインは続行する
        }
    }

    /**
     * @param string $message 画面に出す文言
     * @param string|null $linkAccountId 設定画面から始めていた場合、そのアカウントID
     * @return RedirectResponse
     */
    private function fail(string $message, ?string $linkAccountId = null): RedirectResponse {
        // 設定画面から始めた人をログイン画面に落とさない。ログイン中なのに追い出される
        if ($linkAccountId !== null) {
            return redirect('/settings/connections')->withErrors(['provider' => $message]);
        }

        return redirect('/login')->withErrors(['email' => $message]);
    }
}
