<?php
namespace App\Modules\ExternalLogin\Infrastructure;

use App\Modules\ExternalLogin\Domain\ExternalIdp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 外部 IdP へ行って戻ってくるまでの、セッション上の持ち物。
 *
 * ログイン用と設定画面からの連携用で入口が別なので、鍵の名前と出し入れを
 * ここに集める。両方の入口で同じ鍵を使うためのもので、判断はしない。
 */
class ExternalLoginFlow {
    private const STATE = 'external_login.state';
    private const NONCE = 'external_login.nonce';

    /** 引き取り (claim) 中に連携を始めた場合、戻ってきたときに引き取りへつなげるためのトークン */
    private const CLAIM_TOKEN = 'external_login.claim_token';

    /** 複数の認証主体に紐付いていたときの候補。選ばれるまで持っておく */
    private const CANDIDATES = 'external_login.candidates';

    /** 設定画面から始めた連携。戻ってきたらこのアカウントに足す */
    private const LINK_ACCOUNT = 'external_login.link_account';

    public function __construct(private readonly Request $request) {}

    /**
     * 認可リクエストを組み立て、照合に使う値を預ける。
     *
     * @param ExternalIdp $idp 行き先
     * @param string|null $claimToken 引き取り中ならそのトークン
     * @param string|null $linkAccountId 設定画面からの連携なら、その本人のアカウントID
     * @return string 送り出す先の URL
     */
    public function start(ExternalIdp $idp, ?string $claimToken = null, ?string $linkAccountId = null): string {
        $state = Str::random(40);
        $nonce = Str::random(40);

        $session = $this->request->session();
        $session->put(self::STATE, $state);
        $session->put(self::NONCE, $nonce);

        if ($claimToken !== null && $claimToken !== '') $session->put(self::CLAIM_TOKEN, $claimToken);
        if ($linkAccountId !== null) $session->put(self::LINK_ACCOUNT, $linkAccountId);

        return $idp->authorizationUrl($state, $nonce);
    }

    /**
     * 戻ってきた state が、こちらが出したものと一致するか。
     *
     * 一致しないリクエストは、第三者に開始させられた可能性がある。
     *
     * @param string $state IdP が返してきた state
     * @return bool
     */
    public function matchesState(string $state): bool {
        $expected = $this->request->session()->pull(self::STATE);

        return is_string($expected) && hash_equals($expected, $state);
    }

    /**
     * @return string|null 預けた nonce。無ければ null
     */
    public function pullNonce(): ?string {
        $nonce = $this->request->session()->pull(self::NONCE);

        return is_string($nonce) ? $nonce : null;
    }

    /**
     * @return string|null 引き取り中ならそのトークン
     */
    public function pullClaimToken(): ?string {
        $token = $this->request->session()->pull(self::CLAIM_TOKEN);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @return string|null 設定画面から始めた連携なら、その本人のアカウントID
     */
    public function pullLinkAccountId(): ?string {
        $id = $this->request->session()->pull(self::LINK_ACCOUNT);

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * 選択画面を挟むあいだ、候補と引き取りトークンを預け直す。
     *
     * @param list<string> $candidates 紐付いている認証主体のID
     * @param string|null $claimToken 引き取り中ならそのトークン
     * @return void
     */
    public function keepCandidates(array $candidates, ?string $claimToken): void {
        $this->request->session()->put(self::CANDIDATES, $candidates);
        if ($claimToken !== null) $this->request->session()->put(self::CLAIM_TOKEN, $claimToken);
    }

    /**
     * @return list<string> 候補が無ければ空
     */
    public function candidates(): array {
        $candidates = $this->request->session()->get(self::CANDIDATES);
        if (!is_array($candidates)) return [];

        return array_values(array_filter($candidates, is_string(...)));
    }

    /**
     * @return void
     */
    public function forgetCandidates(): void {
        $this->request->session()->forget(self::CANDIDATES);
    }
}
