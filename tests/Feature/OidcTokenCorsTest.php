<?php
namespace Tests\Feature;

use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class OidcTokenCorsTest extends TestCase {
    #[TestDox('任意のOriginから資格情報なしでOIDCトークン交換できる')]
    public function test_allowsPreflightFromAnyOriginWithoutCredentials(): void {
        $response = $this->withHeaders([
            'Origin' => 'https://arbitrary.example',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->options('/oauth/token');

        $response->assertNoContent();
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }

    #[TestDox('任意のOriginから資格情報なしでOIDC署名鍵を取得できる')]
    public function test_allowsJwksFromAnyOriginWithoutCredentials(): void {
        $response = $this->withHeaders([
            'Origin' => 'https://plugins.wevocal.pitan76.net',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/oauth/jwks');

        $response->assertNoContent();
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }

    #[TestDox('トークン交換以外のエンドポイントではOriginを許可しない')]
    public function test_doesNotEnableCorsForOtherEndpoints(): void {
        $response = $this->withHeaders([
            'Origin' => 'https://arbitrary.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/oauth/userinfo');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
