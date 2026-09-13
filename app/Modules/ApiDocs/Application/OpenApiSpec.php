<?php
namespace App\Modules\ApiDocs\Application;

use App\Modules\ApiDocs\Application\Paths\OidcPaths;
use App\Modules\ApiDocs\Application\Paths\ServiceAccountPaths;
use App\Modules\ApiDocs\Application\Paths\ServiceAuthPaths;
use App\Modules\ApiDocs\Application\Paths\SpecPaths;

/**
 * ChreeID の OpenAPI 仕様を組み立てる。/api/v1/openapi.json として配り、Scalar が画面にする。
 *
 * 経路を足したら Paths の側にも足すこと。書き漏れは OpenApiSpecTest が
 * ルート一覧との突き合わせで落とす (実装だけ変わって仕様が古いまま、を防ぐため)。
 */
class OpenApiSpec {
    /** @var list<SpecPaths> */
    private readonly array $groups;

    public function __construct(ServiceAccountPaths $accounts, ServiceAuthPaths $auth, OidcPaths $oidc) {
        $this->groups = [$accounts, $auth, $oidc];
    }

    /**
     * @param string $issuer ChreeID の公開 URL
     * @return array<string, mixed>
     */
    public function build(string $issuer): array {
        $paths = [];
        foreach ($this->groups as $group) $paths = array_merge($paths, $group->paths());

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'ChreeID API',
                'version' => '1.0.0',
                'description' => SpecParts::t('apidocs.info.description'),
            ],
            'servers' => [['url' => rtrim($issuer, '/')]],
            'tags' => [
                ['name' => 'Service accounts', 'description' => SpecParts::t('apidocs.tag.service_accounts')],
                ['name' => 'Service auth', 'description' => SpecParts::t('apidocs.tag.service_auth')],
                ['name' => 'OpenID Connect', 'description' => SpecParts::t('apidocs.tag.oidc')],
            ],
            'paths' => $paths,
            'components' => $this->components(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function components(): array {
        return [
            'securitySchemes' => [
                'clientBasic' => [
                    'type' => 'http',
                    'scheme' => 'basic',
                    'description' => SpecParts::t('apidocs.security.client'),
                ],
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'description' => SpecParts::t('apidocs.security.bearer'),
                ],
            ],
            'schemas' => [
                'Error' => SpecParts::object([
                    'error' => ['type' => 'string', 'example' => 'invalid_client'],
                    'error_description' => ['type' => 'string'],
                    'errors' => [
                        'type' => 'object',
                        'description' => SpecParts::t('apidocs.error.fields'),
                        'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ], ['error', 'error_description']),
            ],
        ];
    }
}
