<?php
namespace App\Modules\Provider\Domain;

use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 検証を通った認可リクエスト。
 *
 * 生のクエリ文字列をそのまま持ち回らず、確認済みの値だけをここに詰める。
 */
readonly class AuthorizeRequest {
    public OAuthClientModel $client;
    public string $redirectUri;
    /** @var list<string> */
    public array $scopes;
    public ?string $state;
    public ?string $nonce;
    public ?string $codeChallenge;
    public ?string $codeChallengeMethod;

    /**
     * @param OAuthClientModel $client 接続先サービス
     * @param string $redirectUri 登録済みと完全一致したリダイレクト先
     * @param list<string> $scopes 要求スコープ
     * @param string|null $state RP が渡した値。そのまま返す
     * @param string|null $nonce id_token に載せる値
     * @param string|null $codeChallenge PKCE
     * @param string|null $codeChallengeMethod PKCE。S256 のみ
     */
    public function __construct(
        OAuthClientModel $client,
        string $redirectUri,
        array $scopes,
        ?string $state,
        ?string $nonce,
        ?string $codeChallenge,
        ?string $codeChallengeMethod,
    ) {
        $this->client = $client;
        $this->redirectUri = $redirectUri;
        $this->scopes = $scopes;
        $this->state = $state;
        $this->nonce = $nonce;
        $this->codeChallenge = $codeChallenge;
        $this->codeChallengeMethod = $codeChallengeMethod;
    }
}
