<?php
namespace App\Modules\ExternalLogin\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Audit\Domain\LoginMethod;
use App\Modules\ExternalLogin\Application\LinkExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\ExternalLogin\Infrastructure\ExternalLoginFlow;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Application\ClaimServiceAccount;
use App\Modules\Linking\Application\ClaimTickets;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * 外部 IdP から戻ってきたあとの着地 (ChreeID が RP 側)。
 *
 * 認可コードで戻る IdP は ExternalLoginController が、SAML のように別の形で戻る IdP は
 * プラグインが受けてここへ渡す。state の照合から先を1か所にまとめないと、
 * 入口ごとに照合やアカウント停止の確認を取りこぼす。
 */
class ExternalLoginLanding {
    private readonly LinkExternalIdentity $link;
    private readonly ChreeSession $session;
    private readonly ClaimTickets $tickets;
    private readonly ClaimServiceAccount $claim;
    private readonly AuthIdentityRepository $accounts;
    private readonly ExternalLoginFlow $flow;
    private readonly AuditLog $audit;

    public function __construct(LinkExternalIdentity $link, ChreeSession $session, ClaimTickets $tickets, ClaimServiceAccount $claim, AuthIdentityRepository $accounts, ExternalLoginFlow $flow, AuditLog $audit) {
        $this->link = $link;
        $this->session = $session;
        $this->tickets = $tickets;
        $this->claim = $claim;
        $this->accounts = $accounts;
        $this->flow = $flow;
        $this->audit = $audit;
    }

    /**
     * state を照合し、IdP の応答を確かめて、ログインか連携を済ませる。
     *
     * @param string $provider IdP の識別子。ログに残す
     * @param string $state IdP が返してきた state (SAML なら RelayState)
     * @param Closure(string): ExternalIdentity $verify 発行時の nonce を受け取り、応答を確かめて外部アカウントを返す。
     *   届かなければ ConnectionException、拒否なら RuntimeException を投げる
     * @return RedirectResponse
     */
    public function finish(string $provider, string $state, Closure $verify): RedirectResponse {
        $linkAccountId = $this->flow->pullLinkAccountId();
        $nonce = $this->flow->pullNonce();
        $claimToken = $this->flow->pullClaimToken();

        if (!$this->flow->matchesState($state) || $nonce === null) {
            return $this->fail(__('auth.external_login.link_failed'), $linkAccountId);
        }

        $identity = $this->verify($provider, $verify, $nonce);
        if (is_string($identity)) return $this->fail($identity, $linkAccountId);

        // 設定画面から始めた連携は、ログインではなく本人のアカウントへ足すだけ
        if ($linkAccountId !== null) return $this->addToAccount($linkAccountId, $identity);

        return $this->loginWith($identity, $claimToken);
    }

    /**
     * 利用者が途中でやめたなど、応答を確かめるまでもなく失敗したとき。
     *
     * 預けていた値は捨てる。残すと、次の開始と取り違える。
     *
     * @param string $message 画面に出す文言
     * @return RedirectResponse
     */
    public function abort(string $message): RedirectResponse {
        $linkAccountId = $this->flow->pullLinkAccountId();
        $this->flow->forget();

        return $this->fail($message, $linkAccountId);
    }

    /**
     * IdP の応答を確かめる。外部との通信境界なのでここでだけ捕まえる。
     *
     * 利用者に出す文言は「届かなかった」と「断られた」の2つに分ける。
     * 前者は時間をおけば通るが、後者はやり直し方が違う。詳しい原因はログに残す。
     *
     * @param string $provider
     * @param Closure(string): ExternalIdentity $verify
     * @param string $nonce 発行時の nonce
     * @return ExternalIdentity|string 成功なら外部アカウント、失敗なら画面に出す文言
     */
    private function verify(string $provider, Closure $verify, string $nonce): ExternalIdentity|string {
        try {
            return $verify($nonce);
        } catch (ConnectionException $e) {
            Log::warning("external login unreachable: {$provider}", ['exception' => $e]);

            return __('auth.external_login.unreachable');
        } catch (RuntimeException $e) {
            Log::warning("external login rejected: {$provider}", ['exception' => $e]);

            return __('auth.external_login.failed');
        }
    }

    /**
     * ログイン経路。連携先のアカウントを決めてセッションを張る。
     *
     * @param ExternalIdentity $identity IdP が主張してきた内容
     * @param string|null $claimToken 引き取り中ならそのトークン
     * @return RedirectResponse
     */
    private function loginWith(ExternalIdentity $identity, ?string $claimToken): RedirectResponse {
        // 分離すると、同じ外部アカウントが複数の認証主体に紐付きうる。
        // 黙ってどれかを選ぶと別のアカウントとして入れてしまうので、本人に選ばせる
        $candidates = $this->link->candidates($identity);
        if (count($candidates) > 1) {
            $this->flow->keepCandidates($candidates, $claimToken);

            return redirect('/login/choose');
        }

        try {
            $accountId = $this->link->execute($identity);
        } catch (ExternalIdentityConflict) {
            return $this->fail(__('auth.external_login.link_existing_account_first'));
        }

        return $this->completeLogin($accountId, LoginMethod::OAUTH->with($identity->provider), $claimToken);
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
        }

        $this->audit->record(AuditAction::CONNECTION_ADDED, $linkAccountId, ['provider' => $identity->provider]);

        return redirect('/settings/connections')->with('connectionAdded', true);
    }

    /**
     * 使えるアカウントであることを確かめてからセッションを張る。
     *
     * 自動で決まった経路と本人が選んだ経路の両方がここを通る。
     * 片方だけ停止を見る取りこぼしを起こさないため。
     *
     * @param string $accountId ログインさせる認証主体
     * @param string $method 監査に残すログイン方法
     * @param string|null $claimToken 引き取り中ならそのトークン
     * @return RedirectResponse
     */
    public function completeLogin(string $accountId, string $method, ?string $claimToken): RedirectResponse {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->isSuspended()) return $this->fail(__('auth.external_login.account_unusable'));

        if ($claimToken !== null) $this->finalizeClaim($claimToken, $accountId);

        $this->session->login($accountId, $method);

        return redirect('/');
    }

    /**
     * 引き取り中だった場合、外部IdPの連携そのものを認証手段として引き取りを完了する。
     *
     * 連携で解決したアカウントが引き取り対象と別人のものだった場合は、
     * 引き取りには繋げず通常ログインとして扱う (メールが一致しなかった等)。
     *
     * @param string $claimToken
     * @param string $accountId 連携で解決したアカウントID
     */
    private function finalizeClaim(string $claimToken, string $accountId): void {
        $link = $this->tickets->find($claimToken);
        if ($link === null || $link->isClaimed() || $link->auth_identity_id !== $accountId) return;

        try {
            $this->claim->executeWithExistingCredential($link);
        } catch (Throwable $e) {
            // 引き取りが不成立でも、連携自体は済んでいるのでログインは続行する。
            // ただし黙ると移行が進まない原因を追えないので残す
            Log::warning('claim after external login failed', ['exception' => $e]);
        }
    }

    /**
     * @param string $message 画面に出す文言
     * @param string|null $linkAccountId 設定画面から始めていた場合、そのアカウントID
     * @return RedirectResponse
     */
    public function fail(string $message, ?string $linkAccountId = null): RedirectResponse {
        // 設定画面から始めた人をログイン画面に落とさない。ログイン中なのに追い出される
        if ($linkAccountId !== null) {
            return redirect('/settings/connections')->withErrors(['provider' => $message]);
        }

        return redirect('/login')->withErrors(['email' => $message]);
    }
}
