<?php
namespace App\Modules\ApiDocs\Application;

/**
 * OpenAPI の部品。どのパスでも同じ形で書くために1箇所に置く。
 */
final class SpecParts {
    /** クライアント認証が要る操作の security */
    public const CLIENT_AUTH = [['clientBasic' => []]];

    /**
     * 表示文言を引く。仕様の文言も翻訳の正 (lang/*.json) に置く。
     *
     * @param string $key 翻訳キー
     * @param array<string, string> $replace 置き換え
     * @return string
     */
    public static function t(string $key, array $replace = []): string {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }

    /**
     * 呼び出し元はフォームで送ってくる (DokuFarm は x-www-form-urlencoded)。JSON も受け付ける。
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function body(array $schema): array {
        return [
            'required' => true,
            'content' => [
                'application/x-www-form-urlencoded' => ['schema' => $schema],
                'application/json' => ['schema' => $schema],
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    public static function object(array $properties, array $required = []): array {
        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) $schema['required'] = $required;

        return $schema;
    }

    /**
     * @param string $descriptionKey 説明の翻訳キー
     * @param array<string, mixed> $extra 追加の制約
     * @return array<string, mixed>
     */
    public static function field(string $descriptionKey, array $extra = []): array {
        return array_merge(['type' => 'string', 'description' => self::t($descriptionKey)], $extra);
    }

    /**
     * @param string $descriptionKey 説明の翻訳キー
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function json(string $descriptionKey, array $schema): array {
        return [
            'description' => self::t($descriptionKey),
            'content' => ['application/json' => ['schema' => $schema]],
        ];
    }

    /**
     * @param string $descriptionKey 説明の翻訳キー
     * @return array<string, mixed>
     */
    public static function error(string $descriptionKey): array {
        return [
            'description' => self::t($descriptionKey),
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
        ];
    }

    /**
     * EnsureProvisioningClient と入力検証が返しうる失敗。どのサーバ間 API にも付く。
     *
     * @return array<int, array<string, mixed>> HTTP ステータス => 応答
     */
    public static function clientErrors(): array {
        return [
            '401' => self::error('apidocs.error.invalid_client'),
            '403' => self::error('apidocs.error.access_denied'),
            '422' => self::error('apidocs.error.invalid_request'),
        ];
    }

    /**
     * @return array{type: string, properties: array<string, mixed>, required: list<string>}
     */
    public static function serviceUserBody(): array {
        return [
            'type' => 'object',
            'properties' => ['service_user_id' => self::serviceUserId()],
            'required' => ['service_user_id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serviceUserId(): array {
        return self::field('apidocs.field.service_user_id', ['maxLength' => 190, 'example' => '42']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function noContent(): array {
        return ['description' => self::t('apidocs.response.no_content')];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ok(): array {
        return self::json('apidocs.response.ok', self::object(['ok' => ['type' => 'boolean', 'const' => true]]));
    }
}
