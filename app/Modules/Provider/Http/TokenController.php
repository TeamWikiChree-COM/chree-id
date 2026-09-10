<?php
namespace App\Modules\Provider\Http;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\ResolveSubject;
use App\Modules\Provider\Domain\Claims\ScopeRegistry;
use App\Modules\Provider\Infrastructure\AccessTokenModel;
use App\Modules\Provider\Infrastructure\AuthCodeModel;
use App\Modules\Provider\Infrastructure\IdTokenIssuer;
use App\Modules\Registry\Application\AuthenticateClient;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * OIDC のトークンエンドポイント。
 *
 * 認可コードを ID Token とアクセストークンに交換する。
 */
class TokenController {
    /** アクセストークンの寿命(秒) */
    private const ACCESS_TOKEN_TTL = 3600;

    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ResolveSubject $subjects,
        private readonly IdTokenIssuer $idTokens,
        private readonly ScopeRegistry $scopes,
        private readonly AuthenticateClient $clients,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse {
        if ($request->string('grant_type')->toString() !== 'authorization_code') {
            return $this->error('unsupported_grant_type', 'grant_type は authorization_code のみ対応しています');
        }

        $client = $this->clients->execute($request);
        if ($client === null) return $this->error('invalid_client', 'クライアント認証に失敗しました', 401);

        $code = $request->string('code')->toString();
        $row = AuthCodeModel::query()->find(hash('sha256', $code));
        if ($row === null || $row->client_id !== $client->id) {
            return $this->error('invalid_grant', '認可コードが不正です');
        }

        // 一度使ったコードが再び来たら、そのコードから出たトークンをまとめて失効させる (RFC 6749 4.1.2)
        if ($row->used_at !== null) {
            AccessTokenModel::query()
                ->where('auth_code_hash', $row->code_hash)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $this->error('invalid_grant', '認可コードが再利用されました');
        }

        if (!$row->isUsable()) return $this->error('invalid_grant', '認可コードの期限が切れています');
        if ($row->redirect_uri !== $request->string('redirect_uri')->toString()) {
            return $this->error('invalid_grant', 'redirect_uri が発行時と一致しません');
        }

        $pkceError = $this->verifyPkce($row, $request->string('code_verifier')->toString());
        if ($pkceError !== null) return $pkceError;

        $row->forceFill(['used_at' => now()])->save();

        return $this->issueTokens($client, $row);
    }

    /**
     * @param AuthCodeModel $row
     * @param string $verifier
     * @return JsonResponse|null 問題なければ null
     */
    private function verifyPkce(AuthCodeModel $row, string $verifier): ?JsonResponse {
        if ($row->code_challenge === null) return null;

        if ($verifier === '') return $this->error('invalid_grant', 'code_verifier が必要です');

        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($row->code_challenge, $expected)
            ? null
            : $this->error('invalid_grant', 'code_verifier が一致しません');
    }

    /**
     * @param OAuthClientModel $client
     * @param AuthCodeModel $row
     * @return JsonResponse
     */
    private function issueTokens(OAuthClientModel $client, AuthCodeModel $row): JsonResponse {
        $account = $this->accounts->findById($row->auth_identity_id);
        if ($account === null) return $this->error('invalid_grant', 'アカウントが見つかりません');

        // 認可のときに決めたサービスアカウントで sub を出す。
        // 入れ替え前に出したコードには載っていないので、その場合だけ認証主体から引く
        $serviceAccount = $row->service_account_id === null
            ? null
            : ServiceAccountModel::query()->find($row->service_account_id);

        $subject = $serviceAccount === null
            ? $this->subjects->execute($client, $account->id)
            : $this->subjects->forServiceAccount($serviceAccount);
        $scopes = array_values(array_filter(explode(' ', $row->scope), static fn (string $s): bool => $s !== ''));

        $accessToken = Str::random(64);
        AccessTokenModel::create([
            'token_hash' => hash('sha256', $accessToken),
            'client_id' => $client->id,
            'auth_identity_id' => $account->id,
            'service_account_id' => $serviceAccount?->id,
            'scope' => $row->scope,
            'auth_code_hash' => $row->code_hash,
            'expires_at' => now()->addSeconds(self::ACCESS_TOKEN_TTL),
        ]);

        $idToken = $this->idTokens->issue(
            $client->id,
            $subject,
            $row->nonce,
            $this->scopes->claimsFor($account, $scopes),
        );

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL,
            'id_token' => $idToken,
            'scope' => $row->scope,
        ]);
    }

    /**
     * @param string $error OAuth のエラーコード
     * @param string $description 説明
     * @param int $status HTTP ステータス
     * @return JsonResponse
     */
    private function error(string $error, string $description, int $status = 400): JsonResponse {
        return response()->json(['error' => $error, 'error_description' => $description], $status);
    }
}
