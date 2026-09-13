<?php
namespace App\Modules\ExternalLogin\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Audit\Domain\LoginMethod;
use App\Modules\ExternalLogin\Application\LinkExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\ExternalLogin\Domain\ExternalIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\ExternalLogin\Infrastructure\ExternalLoginFlow;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\ClaimServiceAccount;
use App\Modules\Linking\Application\ClaimTickets;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
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

        $identity = $this->exchange($idp, $code, $nonce);
        if (is_string($identity)) return $this->fail($identity, $linkAccountId);

        // 設定画面から始めた連携は、ログインではなく本人のアカウントへ足すだけ
        if ($linkAccountId !== null) return $this->addToAccount($linkAccountId, $identity);

        return $this->loginWith($identity, $claimToken);
    }

    /**
     * 認可コードを交換する。外部との通信境界なのでここでだけ捕まえる。
     *
     * 利用者に出す文言は「届かなかった」と「断られた」の2つに分ける。
     * 前者は時間をおけば通るが、後者はやり直し方が違う。詳しい原因はログに残す。
     *
     * @param ExternalIdp $idp
     * @param string $code 認可コード
     * @param string $nonce 発行時の nonce
     * @return ExternalIdentity|string 成功なら外部アカウント、失敗なら画面に出す文言
     */
    private function exchange(ExternalIdp $idp, string $code, string $nonce): ExternalIdentity|string {
        try {
            return $idp->exchange($code, $nonce);
        } catch (ConnectionException $e) {
            Log::warning("external login unreachable: {$idp->name()}", ['exception' => $e]);

            return __('auth.external_login.unreachable');
        } catch (RuntimeException $e) {
            Log::warning("external login rejected: {$idp->name()}", ['exception' => $e]);

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
        if (!\in_array($chosen, $candidates, true)) return $this->fail(__('auth.external_login.link_failed'));

        $this->flow->forgetCandidates();

        return $this->completeLogin($chosen, LoginMethod::OAUTH->value, $this->flow->pullClaimToken());
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
    private function completeLogin(string $accountId, string $method, ?string $claimToken): RedirectResponse {
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
     * @return void
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
    private function fail(string $message, ?string $linkAccountId = null): RedirectResponse {
        // 設定画面から始めた人をログイン画面に落とさない。ログイン中なのに追い出される
        if ($linkAccountId !== null) {
            return redirect('/settings/connections')->withErrors(['provider' => $message]);
        }

        return redirect('/login')->withErrors(['email' => $message]);
    }
}
