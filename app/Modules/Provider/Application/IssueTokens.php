<?php
namespace App\Modules\Provider\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Domain\Claims\ScopeRegistry;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Provider\Infrastructure\AuthCodeModel;
use App\Modules\Provider\Infrastructure\IdTokenIssuer;
use Illuminate\Support\Str;

/**
 * 検証済みの認可コードから、アクセストークンと ID Token を発行する。
 */
class IssueTokens {
    /** アクセストークンの寿命(秒) */
    private const ACCESS_TOKEN_TTL = 3600;

    private readonly AuthIdentityRepository $accounts;
    private readonly ResolveSubject $subjects;
    private readonly IdTokenIssuer $idTokens;
    private readonly ScopeRegistry $scopes;

    public function __construct(
        AuthIdentityRepository $accounts,
        ResolveSubject $subjects,
        IdTokenIssuer $idTokens,
        ScopeRegistry $scopes,
    ) {
        $this->accounts = $accounts;
        $this->subjects = $subjects;
        $this->idTokens = $idTokens;
        $this->scopes = $scopes;
    }

    /**
     * @param OAuthClientModel $client 認証済みのクライアント
     * @param AuthCodeModel $code 検証済みの認可コード
     * @return IssuedTokens
     * @throws TokenException アカウントが消えていた場合
     */
    public function execute(OAuthClientModel $client, AuthCodeModel $code): IssuedTokens {
        $account = $this->accounts->findById($code->auth_identity_id);
        if ($account === null) throw TokenException::invalidGrant('account_not_found');

        // 認可のときに決めたサービスアカウントで sub を出す。
        // 入れ替え前に出したコードには載っていないので、その場合だけ認証主体から引く
        $serviceAccount = $code->service_account_id === null
            ? null
            : ServiceAccountModel::query()->find($code->service_account_id);

        $subject = $serviceAccount === null
            ? $this->subjects->execute($client, $account->id)
            : $this->subjects->forServiceAccount($serviceAccount);
        $scopes = array_values(array_filter(explode(' ', $code->scope), static fn (string $s): bool => $s !== ''));

        $accessToken = $this->storeAccessToken($client, $code, $serviceAccount?->id);

        $idToken = $this->idTokens->issue(
            $client->id,
            $subject,
            $code->nonce,
            $this->scopes->claimsFor($account, $scopes, $serviceAccount?->id),
        );

        return new IssuedTokens($accessToken, $idToken, $code->scope, self::ACCESS_TOKEN_TTL);
    }

    /**
     * @param OAuthClientModel $client 認証済みのクライアント
     * @param AuthCodeModel $code 検証済みの認可コード
     * @param string|null $serviceAccountId 渡す先のサービスアカウント
     * @return string 平文のアクセストークン
     */
    private function storeAccessToken(OAuthClientModel $client, AuthCodeModel $code, ?string $serviceAccountId): string {
        $accessToken = Str::random(64);

        AccessTokenModel::create([
            'token_hash' => hash('sha256', $accessToken),
            'client_id' => $client->id,
            'auth_identity_id' => $code->auth_identity_id,
            'service_account_id' => $serviceAccountId,
            'scope' => $code->scope,
            'auth_code_hash' => $code->code_hash,
            'expires_at' => now()->addSeconds(self::ACCESS_TOKEN_TTL),
        ]);

        return $accessToken;
    }
}
