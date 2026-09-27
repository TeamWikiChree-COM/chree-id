<?php
namespace Tests\Unit;

use App\Modules\ExternalLogin\Infrastructure\IdTokenClaims;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// 外部 IdP の id_token の確かめ方 (Google、Yahoo! JAPAN などで共通)
class IdTokenClaimsTest extends TestCase {
    private const ISSUER = 'https://idp.example.com';
    private const CLIENT = 'our-client';

    /**
     * @param array<string, mixed> $overrides
     * @return string 署名は見ないので、署名部はでたらめでよい
     */
    private function token(array $overrides = []): string {
        $claims = $overrides + ['iss' => self::ISSUER, 'aud' => self::CLIENT, 'sub' => 'u-1', 'nonce' => 'n', 'exp' => time() + 60];
        $encode = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $encode('{"alg":"RS256"}') . '.' . $encode((string) json_encode($claims)) . '.sig';
    }

    /**
     * @param string $token
     * @return array<string, mixed>
     */
    private function read(string $token): array {
        return (new IdTokenClaims())->read($token, [self::ISSUER], self::CLIENT, 'n');
    }

    #[TestDox('aud は文字列でも、自分だけが入った配列でも受ける')]
    public function test_acceptsStringOrSingleArrayAudience(): void {
        $this->assertSame('u-1', $this->read($this->token())['sub']);
        $this->assertSame('u-1', $this->read($this->token(['aud' => [self::CLIENT]]))['sub']);
    }

    #[TestDox('aud に複数の相手がいるなら、azp が自分でなければ受けない')]
    public function test_multipleAudiencesNeedAzp(): void {
        $this->assertSame('u-1', $this->read($this->token(['aud' => [self::CLIENT, 'other'], 'azp' => self::CLIENT]))['sub']);

        $this->expectException(RuntimeException::class);
        $this->read($this->token(['aud' => [self::CLIENT, 'other']]));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidClaims(): array {
        return [
            'ほかの発行者' => [['iss' => 'https://evil.example.com']],
            'ほかの宛先' => [['aud' => 'other']],
            'nonce が違う' => [['nonce' => 'x']],
            '期限切れ' => [['exp' => time() - 1]],
            'sub が無い' => [['sub' => '']],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[TestDox('発行者・宛先・nonce・期限・sub のどれかが合わなければ受けない')]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidClaims')]
    public function test_rejectsInvalidClaims(array $overrides): void {
        $this->expectException(RuntimeException::class);
        $this->read($this->token($overrides));
    }

    #[TestDox('JWT の形でなければ受けない')]
    public function test_rejectsMalformedToken(): void {
        $this->expectException(RuntimeException::class);
        $this->read('not-a-jwt');
    }
}
