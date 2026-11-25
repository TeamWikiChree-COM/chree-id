<?php
namespace App\Modules\Linking\Domain;

/**
 * サービス経由のパスワード照会の結果と、成立したときだけ付く識別子。
 */
final class ServiceAuthResult {
    /**
     * @param ServiceAuthOutcome $outcome 結果
     * @param string|null $sub 成立時にサービスへ渡す sub
     * @param string|null $serviceUserId サービス側での利用者の識別子
     * @param string|null $challenge 2要素目を受け取る一度きりの合図 (SECOND_FACTOR_REQUIRED のときだけ)
     */
    private function __construct(
        public readonly ServiceAuthOutcome $outcome,
        public readonly ?string $sub = null,
        public readonly ?string $serviceUserId = null,
        public readonly ?string $challenge = null,
    ) {}

    /**
     * @param string $sub サービスへ渡す sub
     * @param string|null $serviceUserId サービス側での利用者の識別子。
     *                                   OIDC 経由で先にできたサービスアカウントでは分からない
     * @return self
     */
    public static function ok(string $sub, ?string $serviceUserId): self {
        return new self(ServiceAuthOutcome::OK, $sub, $serviceUserId);
    }

    /**
     * @return self
     */
    public static function invalid(): self {
        return new self(ServiceAuthOutcome::INVALID);
    }

    /**
     * @param string $challenge 2要素目と一緒に送り返してもらう合図
     * @return self
     */
    public static function secondFactorRequired(string $challenge): self {
        return new self(ServiceAuthOutcome::SECOND_FACTOR_REQUIRED, challenge: $challenge);
    }
}
