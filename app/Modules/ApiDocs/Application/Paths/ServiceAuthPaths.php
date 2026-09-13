<?php
namespace App\Modules\ApiDocs\Application\Paths;

use App\Modules\ApiDocs\Application\SpecParts as P;
use App\Modules\Linking\Domain\ServiceAuthOutcome;

/**
 * /api/v1/service-auth/*。サービスが自前の画面のまま、照合だけ ChreeID に任せる口。
 */
class ServiceAuthPaths implements SpecPaths {
    private const TAG = ['Service auth'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function paths(): array {
        return [
            '/api/v1/service-auth/password' => ['post' => $this->verifyPassword()],
            '/api/v1/service-auth/magic-link' => ['post' => $this->issueMagicLink()],
            '/api/v1/service-auth/magic-link/consume' => ['post' => $this->consumeMagicLink()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyPassword(): array {
        $body = P::object([
            'email' => P::field('apidocs.field.email', ['format' => 'email', 'maxLength' => 255]),
            'password' => P::field('apidocs.field.password', ['format' => 'password']),
        ], ['email', 'password']);

        return $this->operation('verify_password', $body, [
            '200' => P::json('apidocs.verify_password.result', P::object([
                // 選べる値は実装の enum から出す。INVALID は 401 で返すので載せない
                'status' => ['type' => 'string', 'enum' => [
                    ServiceAuthOutcome::OK->value,
                    ServiceAuthOutcome::SECOND_FACTOR_REQUIRED->value,
                ], 'description' => P::t('apidocs.verify_password.status')],
                'sub' => ['type' => 'string'],
                'service_user_id' => ['type' => 'string'],
            ], ['status'])),
            '401' => P::error('apidocs.verify_password.invalid'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function issueMagicLink(): array {
        $body = P::object(['email' => P::field('apidocs.field.email', ['format' => 'email', 'maxLength' => 255])], ['email']);

        return $this->operation('magic_link', $body, [
            '200' => P::json('apidocs.magic_link.issued', P::object(['token' => ['type' => 'string']], ['token'])),
            '404' => P::error('apidocs.magic_link.not_applicable'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function consumeMagicLink(): array {
        $body = P::object(['token' => P::field('apidocs.field.magic_token', ['maxLength' => 128])], ['token']);

        return $this->operation('magic_consume', $body, [
            '200' => P::json('apidocs.magic_consume.consumed', P::object(['service_user_id' => P::serviceUserId()], ['service_user_id'])),
            '401' => P::error('apidocs.magic_consume.invalid'),
        ]);
    }

    /**
     * @param string $name 翻訳キーの操作名
     * @param array<string, mixed> $body
     * @param array<int, array<string, mixed>> $responses HTTP ステータス => 操作に固有の応答
     * @return array<string, mixed>
     */
    private function operation(string $name, array $body, array $responses): array {
        return [
            'tags' => self::TAG,
            'summary' => P::t("apidocs.{$name}.summary"),
            'description' => P::t("apidocs.{$name}.description"),
            'security' => P::CLIENT_AUTH,
            'requestBody' => P::body($body),
            // 操作に固有の応答を優先する。401 は照合の失敗とクライアント認証の失敗の両方で返る
            'responses' => $responses + P::clientErrors() + [
                '429' => P::error('apidocs.error.too_many_requests'),
            ],
        ];
    }
}
