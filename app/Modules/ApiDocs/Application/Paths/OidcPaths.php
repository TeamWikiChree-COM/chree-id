<?php
namespace App\Modules\ApiDocs\Application\Paths;

use App\Modules\ApiDocs\Application\SpecParts as P;

/**
 * OIDC のサーバ間エンドポイント。
 *
 * /oauth/authorize はブラウザで開く画面なので載せない。RP の組み方は
 * ディスカバリ (/.well-known/openid-configuration) に従ってもらう。
 */
class OidcPaths implements SpecPaths {
    private const TAG = ['OpenID Connect'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function paths(): array {
        return [
            '/.well-known/openid-configuration' => ['get' => $this->plain('discovery')],
            '/oauth/jwks' => ['get' => $this->plain('jwks')],
            '/oauth/token' => ['post' => $this->token()],
            '/oauth/userinfo' => ['get' => $this->userinfo()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function token(): array {
        $body = P::object([
            'grant_type' => ['type' => 'string', 'enum' => ['authorization_code']],
            'code' => ['type' => 'string'],
            'redirect_uri' => ['type' => 'string', 'format' => 'uri'],
            'code_verifier' => P::field('apidocs.field.code_verifier'),
            'client_id' => P::field('apidocs.field.client_id'),
            'client_secret' => P::field('apidocs.field.client_secret'),
        ], ['grant_type', 'code', 'redirect_uri']);

        return [
            'tags' => self::TAG,
            'summary' => P::t('apidocs.token.summary'),
            'description' => P::t('apidocs.token.description'),
            // public クライアントは秘密を持たない (PKCE で守る)
            'security' => array_merge(P::CLIENT_AUTH, [[]]),
            'requestBody' => ['required' => true, 'content' => ['application/x-www-form-urlencoded' => ['schema' => $body]]],
            'responses' => [
                '200' => P::json('apidocs.token.issued', P::object([
                    'access_token' => ['type' => 'string'],
                    'token_type' => ['type' => 'string', 'const' => 'Bearer'],
                    'expires_in' => ['type' => 'integer', 'example' => 3600],
                    'id_token' => ['type' => 'string', 'description' => P::t('apidocs.token.id_token')],
                    'scope' => ['type' => 'string', 'example' => 'openid profile email'],
                ], ['access_token', 'token_type', 'expires_in', 'id_token', 'scope'])),
                '400' => P::error('apidocs.token.invalid'),
                '401' => P::error('apidocs.error.invalid_client'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userinfo(): array {
        return [
            'tags' => self::TAG,
            'summary' => P::t('apidocs.userinfo.summary'),
            'description' => P::t('apidocs.userinfo.description'),
            'security' => [['bearerAuth' => []]],
            'responses' => [
                '200' => P::json('apidocs.userinfo.claims', P::object([
                    'sub' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'email_verified' => ['type' => 'boolean'],
                ], ['sub'])),
                '401' => P::error('apidocs.userinfo.invalid'),
            ],
        ];
    }

    /**
     * 認証の要らない、読むだけの公開エンドポイント。
     *
     * @param string $name 翻訳キーの操作名
     * @return array<string, mixed>
     */
    private function plain(string $name): array {
        return [
            'tags' => self::TAG,
            'summary' => P::t("apidocs.{$name}.summary"),
            'security' => [],
            'responses' => ['200' => P::json("apidocs.{$name}.body", ['type' => 'object'])],
        ];
    }
}
