<?php
namespace App\Modules\ExternalLogin\Http;

use App\Modules\ExternalLogin\Application\LinkExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdentityConflict;
use App\Modules\ExternalLogin\Domain\ExternalIdpRegistry;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\ClaimServiceAccount;
use App\Modules\Linking\Application\ClaimTickets;
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

    /** 引き取り (claim) 中に連携を始めた場合、戻ってきたときに引き取りへつなげるためのトークン */
    private const CLAIM_TOKEN_KEY = 'external_login.claim_token';

    /** 複数の認証主体に紐付いていたときの候補。選ばれるまで持っておく */
    private const CANDIDATES_KEY = 'external_login.candidates';

    public function __construct(
        private readonly ExternalIdpRegistry $registry,
        private readonly LinkExternalIdentity $link,
        private readonly ChreeSession $session,
        private readonly ClaimTickets $tickets,
        private readonly ClaimServiceAccount $claim,
        private readonly AuthIdentityRepository $accounts,
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

        // 引き取り画面から来た場合は、戻ってきたときに分かるよう覚えておく
        $claimToken = $request->string('claim_token')->toString();
        if ($claimToken !== '') $request->session()->put(self::CLAIM_TOKEN_KEY, $claimToken);

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
        $claimToken = $request->session()->pull(self::CLAIM_TOKEN_KEY);

        // state が一致しないリクエストは、第三者に開始させられた可能性がある
        if (!\is_string($state) || !hash_equals($state, $request->string('state')->toString())) {
            return $this->fail('連携を確認できませんでした');
        }
        if (!\is_string($nonce)) return $this->fail('連携を確認できませんでした');

        $code = $request->string('code')->toString();
        if ($code === '') return $this->fail('連携がキャンセルされました');

        try {
            $identity = $idp->exchange($code, $nonce);

            // 分離すると、同じ外部アカウントが複数の認証主体に紐付きうる。
            // 黙ってどれかを選ぶと別のアカウントとして入れてしまうので、本人に選ばせる
            $candidates = $this->link->candidates($identity);
            if (count($candidates) > 1) return $this->chooseIdentity($request, $candidates, $claimToken);

            $accountId = $this->link->execute($identity);
        } catch (ExternalIdentityConflict) {
            return $this->fail('既存のアカウントでログインしてから連携してください');
        } catch (Throwable) {
            return $this->fail('連携に失敗しました');
        }

        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->isSuspended()) return $this->fail('このアカウントは使用できません');

        if (\is_string($claimToken) && $claimToken !== '') {
            $this->finalizeClaim($claimToken, $accountId);
        }

        $this->session->login($accountId);

        return redirect('/');
    }

    /**
     * どの認証主体として入るかを選ばせる画面へ送る。
     *
     * @param Request $request
     * @param list<string> $candidates 紐付いている認証主体のID
     * @param mixed $claimToken 引き取り中ならそのトークン
     * @return RedirectResponse
     */
    private function chooseIdentity(Request $request, array $candidates, mixed $claimToken): RedirectResponse {
        $request->session()->put(self::CANDIDATES_KEY, $candidates);
        if (\is_string($claimToken) && $claimToken !== '') {
            $request->session()->put(self::CLAIM_TOKEN_KEY, $claimToken);
        }

        return redirect('/login/choose');
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
        $candidates = $request->session()->get(self::CANDIDATES_KEY);
        if (!\is_array($candidates) || $candidates === []) return $this->fail('連携を確認できませんでした');

        $chosen = $request->string('account_id')->toString();
        if (!\in_array($chosen, $candidates, true)) return $this->fail('連携を確認できませんでした');

        $account = $this->accounts->findById($chosen);
        if ($account === null || $account->isSuspended()) return $this->fail('このアカウントは使用できません');

        $request->session()->forget(self::CANDIDATES_KEY);
        $claimToken = $request->session()->pull(self::CLAIM_TOKEN_KEY);

        if (\is_string($claimToken) && $claimToken !== '') $this->finalizeClaim($claimToken, $chosen);

        $this->session->login($chosen);

        return redirect('/');
    }

    /**
     * 選択画面。候補が無ければログインへ戻す。
     *
     * @param Request $request
     * @return \Inertia\Response|RedirectResponse
     */
    public function showChoice(Request $request): \Inertia\Response|RedirectResponse {
        $candidates = $request->session()->get(self::CANDIDATES_KEY);
        if (!\is_array($candidates) || $candidates === []) return redirect('/login');

        $accounts = [];
        foreach ($candidates as $id) {
            $account = \is_string($id) ? $this->accounts->findById($id) : null;
            if ($account === null || $account->isSuspended()) continue;

            $accounts[] = [
                'id' => $account->id,
                'email' => $account->email,
                'displayName' => $account->displayName,
            ];
        }

        return \Inertia\Inertia::render('Auth/ChooseIdentity', ['accounts' => $accounts]);
    }

    /**
     * 引き取り中だった場合、Google 連携そのものを認証手段として引き取りを完了する。
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
     * @return RedirectResponse
     */
    private function fail(string $message): RedirectResponse {
        return redirect('/login')->withErrors(['email' => $message]);
    }
}
