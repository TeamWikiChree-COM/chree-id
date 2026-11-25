<?php
namespace App\Modules\ApiDocs\Application\Paths;

use App\Modules\ApiDocs\Application\SpecParts as P;

/**
 * /api/v1/service-accounts/*。サービスが自分の利用者ぶんの ChreeID を扱う口。
 */
class ServiceAccountPaths implements SpecPaths {
    private const TAG = ['Service accounts'];
    private const RESOURCE = '/api/v1/service-accounts/{serviceUserId}';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function paths(): array {
        return [
            self::RESOURCE => [
                'get' => $this->rest('status'),
                'put' => $this->rest('store'),
                'delete' => $this->rest('deactivate'),
            ],
            self::RESOURCE . '/password' => ['put' => $this->rest('password_change')],
            self::RESOURCE . '/totp/secret' => ['post' => $this->rest('totp_start')],
            self::RESOURCE . '/totp' => [
                'put' => $this->rest('totp_enable'),
                'delete' => $this->rest('totp_disable'),
            ],
            self::RESOURCE . '/recovery-codes' => ['post' => $this->rest('recovery_codes')],
            self::RESOURCE . '/claim-tickets' => ['post' => $this->rest('claim_ticket')],
            '/api/v1/subjects/{sub}' => ['delete' => $this->deactivateBySubject()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deactivateBySubject(): array {
        $operation = $this->base('deactivate_by_subject');
        $operation['parameters'] = [[
            'name' => 'sub',
            'in' => 'path',
            'required' => true,
            'description' => P::t('apidocs.param.sub'),
            'schema' => ['type' => 'string'],
        ]];
        $operation['responses'] = [204 => P::noContent(), 404 => P::error('apidocs.error.unknown_service_user')] + P::clientErrors();

        return $operation;
    }

    /**
     * @param string $name 操作名
     * @return array<string, mixed>
     */
    private function rest(string $name): array {
        $definition = $this->definition($name);
        $operation = $this->base($name);
        $operation['parameters'] = [[
            'name' => 'serviceUserId',
            'in' => 'path',
            'required' => true,
            'description' => P::t('apidocs.param.service_user_id'),
            'schema' => P::serviceUserId(),
        ]];

        if ($definition['body'] !== null) {
            $operation['requestBody'] = P::body(P::object($definition['body'], $definition['required']));
        }

        $success = $definition['success'] ?? P::noContent();
        $operation['responses'] = [$definition['status'] => $success] + $definition['errors'] + P::clientErrors();

        return $operation;
    }

    /**
     * @param string $name 操作名
     * @return array<string, mixed>
     */
    private function base(string $name): array {
        return [
            'tags' => self::TAG,
            'summary' => P::t("apidocs.{$name}.summary"),
            'description' => P::t("apidocs.{$name}.description"),
            'security' => P::CLIENT_AUTH,
        ];
    }

    /**
     * @param string $name 操作名
     * @return array{body: array<string, array<string, mixed>>|null, required: list<string>, status: int, success: array<string, mixed>|null, errors: array<int, array<string, mixed>>}
     */
    private function definition(string $name): array {
        $unknown = [404 => P::error('apidocs.error.unknown_service_user')];
        $managed = [409 => P::error('apidocs.totp_start.managed')];
        $codeBody = ['code' => P::field('apidocs.field.second_factor_code', ['maxLength' => 64])];

        return match ($name) {
            'status' => ['body' => null, 'required' => [], 'status' => 200, 'success' => $this->statusResponse(), 'errors' => $unknown],
            'store' => ['body' => $this->storeFields(), 'required' => [], 'status' => 200, 'errors' => [],
                'success' => P::json('apidocs.store.issued', P::object(['sub' => $this->sub()], ['sub']))],
            'deactivate' => ['body' => null, 'required' => [], 'status' => 204, 'success' => null, 'errors' => $unknown],
            'password_change' => [
                'body' => ['password_hash' => P::field('apidocs.field.password_hash', ['maxLength' => 255])],
                'required' => ['password_hash'], 'status' => 204, 'success' => null,
                // 操作に固有の応答を優先する (422 の意味が他と違う)
                'errors' => [
                    404 => P::error('apidocs.password_change.not_found'),
                    409 => P::error('apidocs.password_change.managed'),
                    422 => P::error('apidocs.password_change.unsupported_hash'),
                ],
            ],
            'totp_start' => ['body' => null, 'required' => [], 'status' => 200, 'errors' => $unknown + $managed,
                'success' => P::json('apidocs.totp_start.issued', P::object([
                    'secret' => ['type' => 'string'],
                    'otpauth_uri' => ['type' => 'string'],
                ], ['secret', 'otpauth_uri']))],
            'totp_enable' => [
                'body' => [
                    'secret' => P::field('apidocs.field.totp_secret', ['pattern' => '^[A-Z2-7]{32}$']),
                    'code' => P::field('apidocs.field.second_factor_code', ['maxLength' => 16]),
                ],
                'required' => ['secret', 'code'], 'status' => 200,
                'success' => $this->recoveryCodesResponse('apidocs.totp_enable.enabled'),
                // 操作に固有の応答を優先する (422 の意味が他と違う)
                'errors' => $unknown + $managed + [422 => P::error('apidocs.totp_enable.invalid_code')],
            ],
            'totp_disable' => [
                'body' => $codeBody, 'required' => ['code'], 'status' => 204, 'success' => null,
                'errors' => $unknown + [409 => P::error('apidocs.totp_disable.not_enabled'), 422 => P::error('apidocs.totp_enable.invalid_code')],
            ],
            'recovery_codes' => [
                'body' => $codeBody, 'required' => ['code'], 'status' => 200,
                'success' => $this->recoveryCodesResponse('apidocs.recovery_codes.issued'),
                'errors' => $unknown + [409 => P::error('apidocs.totp_disable.not_enabled'), 422 => P::error('apidocs.totp_enable.invalid_code')],
            ],
            default => ['body' => null, 'required' => [], 'status' => 201, 'success' => $this->claimTicketResponse(),
                'errors' => $unknown + [409 => P::error('apidocs.claim_ticket.already_claimed')]],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function storeFields(): array {
        return [
            'email' => P::field('apidocs.field.email', ['format' => 'email', 'maxLength' => 255]),
            'email_verified' => ['type' => 'boolean', 'default' => false, 'description' => P::t('apidocs.field.email_verified')],
            'display_name' => P::field('apidocs.field.display_name', ['maxLength' => 100]),
            'password_hash' => P::field('apidocs.field.password_hash', ['maxLength' => 255, 'example' => '$2y$10$...']),
            'sub' => P::field('apidocs.field.sub', ['maxLength' => 64]),
            'external' => [
                'type' => 'array',
                'description' => P::t('apidocs.field.external'),
                'items' => ['type' => 'string', 'maxLength' => 190, 'example' => 'google:1234567890'],
            ],
            'magic_link' => ['type' => 'boolean', 'default' => false, 'description' => P::t('apidocs.field.magic_link')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusResponse(): array {
        return P::json('apidocs.status.found', P::object([
            'sub' => $this->sub(),
            'migrated' => ['type' => 'boolean', 'description' => P::t('apidocs.status.migrated')],
            'migrated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'credential_types' => [
                'type' => 'array',
                'description' => P::t('apidocs.status.credential_types'),
                'items' => ['type' => 'string', 'example' => 'password'],
            ],
        ], ['sub', 'migrated', 'migrated_at', 'credential_types']));
    }

    /**
     * @param string $description 応答の説明の翻訳キー
     * @return array<string, mixed>
     */
    private function recoveryCodesResponse(string $description): array {
        return P::json($description, P::object([
            'recovery_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
        ], ['recovery_codes']));
    }

    /**
     * @return array<string, mixed>
     */
    private function claimTicketResponse(): array {
        return P::json('apidocs.claim_ticket.issued', P::object([
            'claim_url' => ['type' => 'string', 'format' => 'uri'],
            'expires_at' => ['type' => 'string', 'format' => 'date-time'],
        ], ['claim_url', 'expires_at']));
    }

    /**
     * @return array<string, mixed>
     */
    private function sub(): array {
        return ['type' => 'string', 'description' => P::t('apidocs.field.sub_issued')];
    }
}
