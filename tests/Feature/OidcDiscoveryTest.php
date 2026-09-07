<?php
namespace Tests\Feature;

use Tests\TestCase;

// OIDC のディスカバリと JWKS
class OidcDiscoveryTest extends TestCase {
    public function test_ディスカバリが仕様どおりのパスで返る(): void {
        $response = $this->getJson('/.well-known/openid-configuration');

        $response->assertOk();
        $response->assertJsonStructure([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'userinfo_endpoint',
            'jwks_uri',
            'response_types_supported',
            'id_token_signing_alg_values_supported',
            'code_challenge_methods_supported',
        ]);
    }

    public function test_issuerは末尾スラッシュを含まない(): void {
        $issuer = $this->getJson('/.well-known/openid-configuration')->json('issuer');

        $this->assertIsString($issuer);
        $this->assertStringEndsNotWith('/', $issuer);
    }

    public function test_PKCEはS256のみを広告する(): void {
        $methods = $this->getJson('/.well-known/openid-configuration')->json('code_challenge_methods_supported');

        $this->assertSame(['S256'], $methods);
    }

    public function test_JWKSが公開鍵を返す(): void {
        $response = $this->getJson('/oauth/jwks');

        $response->assertOk();
        $response->assertJsonStructure(['keys' => [['kty', 'use', 'alg', 'kid', 'n', 'e']]]);
        $this->assertSame('RS256', $response->json('keys.0.alg'));
    }

    public function test_JWKSに秘密鍵の成分が含まれない(): void {
        $key = $this->getJson('/oauth/jwks')->json('keys.0');

        $this->assertIsArray($key);
        foreach (['d', 'p', 'q', 'dp', 'dq', 'qi'] as $secret) {
            $this->assertArrayNotHasKey($secret, $key);
        }
    }
}
