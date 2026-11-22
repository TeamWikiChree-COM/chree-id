<?php
namespace App\Modules\Linking\Infrastructure;

use App\Modules\Linking\Domain\ServiceDeletionAnswer;
use App\Modules\Provider\Infrastructure\JwtSigner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * サービスの削除依頼 URL へ、退会の問い合わせと依頼を送る。
 *
 * 形式は OIDC Back-Channel Logout の logout_token に揃える。
 * RP は既存の検証処理 (JWKS・iss・aud・events) をそのまま流用できる。
 */
class ServiceDeletionEndpoint {
    /** events クレームのキー。サービスはこれを見て退会の通知と判断する */
    public const EVENT = 'urn:chreeid:event:account-withdrawn';

    /** 削除できるかを尋ねるだけ。何も消させない */
    public const ACTION_CHECK = 'check';

    /** 論理削除を依頼する。物理削除はサービスが自分の猶予を過ぎてから行う */
    public const ACTION_DELETE = 'delete';

    /** JWT の有効期間 (秒)。再送のたびに作り直すので短くてよい */
    private const TOKEN_TTL = 120;

    private readonly JwtSigner $signer;

    public function __construct(JwtSigner $signer) {
        $this->signer = $signer;
    }

    /**
     * @param string $uri 送り先
     * @param string $clientId aud に入れる client_id
     * @param string|null $sub OIDC で渡した sub
     * @param string|null $serviceUserId サービス側での識別子。OIDC を通っていない利用者の手がかり
     * @param string $action ACTION_CHECK か ACTION_DELETE
     * @param int $timeout 応答を待つ秒数
     * @return ServiceDeletionAnswer
     * @throws ServiceDeletionUnreachableException 届かない、または応答の形式が合わない場合
     */
    public function send(string $uri, string $clientId, ?string $sub, ?string $serviceUserId, string $action, int $timeout): ServiceDeletionAnswer {
        $token = $this->token($clientId, $sub, $serviceUserId, $action);

        try {
            $response = Http::asForm()->acceptJson()->timeout($timeout)->post($uri, ['withdrawal_token' => $token]);
        } catch (Throwable $e) {
            throw new ServiceDeletionUnreachableException("削除依頼 URL に到達できません: {$uri}", 0, $e);
        }

        $body = $response->json();
        $answer = $response->successful() && is_array($body) ? ServiceDeletionAnswer::fromResponse($body) : null;
        if ($answer === null) throw new ServiceDeletionUnreachableException("削除依頼 URL の応答が不正です: {$uri} (HTTP {$response->status()})");

        return $answer;
    }

    /**
     * @param string $clientId
     * @param string|null $sub
     * @param string|null $serviceUserId
     * @param string $action
     * @return string
     * @throws RuntimeException 署名に失敗した場合
     */
    private function token(string $clientId, ?string $sub, ?string $serviceUserId, string $action): string {
        $now = time();
        $payload = [
            'iss' => $this->signer->issuer(),
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL,
            'jti' => (string) Str::ulid(),
            'events' => [self::EVENT => ['action' => $action]],
        ];

        if ($sub !== null) $payload['sub'] = $sub;
        if ($serviceUserId !== null) $payload['service_user_id'] = $serviceUserId;

        return $this->signer->sign($payload, 'withdrawal+jwt');
    }
}
