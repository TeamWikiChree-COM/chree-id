<?php
namespace Tests\Unit;

use App\Modules\Credential\Infrastructure\Totp;
use PHPUnit\Framework\TestCase;

// TOTP (RFC 6238) の生成と検証
class TotpTest extends TestCase {
    private Totp $totp;

    /**
     * RFC 6238 のテストベクタで使われる鍵 "12345678901234567890" を base32 にしたもの
     */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    #[\Override]
    protected function setUp(): void {
        $this->totp = new Totp();
    }

    /**
     * RFC 6238 Appendix B のテストベクタ (SHA-1 / 8桁のうち下6桁)
     */
    public function test_matchesRfc6238TestVectors(): void {
        $this->assertSame('287082', $this->totp->at(self::RFC_SECRET, intdiv(59, 30)));
        $this->assertSame('081804', $this->totp->at(self::RFC_SECRET, intdiv(1111111109, 30)));
        $this->assertSame('050471', $this->totp->at(self::RFC_SECRET, intdiv(1111111111, 30)));
        $this->assertSame('005924', $this->totp->at(self::RFC_SECRET, intdiv(1234567890, 30)));
        $this->assertSame('279037', $this->totp->at(self::RFC_SECRET, intdiv(2000000000, 30)));
    }

    public function test_acceptsCurrentCode(): void {
        $now = 1700000000;
        $code = $this->totp->at(self::RFC_SECRET, intdiv($now, 30));

        $this->assertTrue($this->totp->verify(self::RFC_SECRET, $code, $now));
    }

    /**
     * 端末の時計が少しずれていても通す
     */
    public function test_acceptsCodeFromAdjacentWindow(): void {
        $now = 1700000000;
        $previous = $this->totp->at(self::RFC_SECRET, intdiv($now, 30) - 1);

        $this->assertTrue($this->totp->verify(self::RFC_SECRET, $previous, $now));
    }

    /**
     * 許容範囲を超えた古いコードは通さない
     */
    public function test_rejectsCodeOutsideWindow(): void {
        $now = 1700000000;
        $old = $this->totp->at(self::RFC_SECRET, intdiv($now, 30) - 5);

        $this->assertFalse($this->totp->verify(self::RFC_SECRET, $old, $now));
    }

    public function test_rejectsMalformedCode(): void {
        $this->assertFalse($this->totp->verify(self::RFC_SECRET, '12345', 1700000000));
        $this->assertFalse($this->totp->verify(self::RFC_SECRET, 'abcdef', 1700000000));
        $this->assertFalse($this->totp->verify(self::RFC_SECRET, '', 1700000000));
    }

    public function test_generatesBase32Secret(): void {
        $secret = $this->totp->generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_buildsProvisioningUri(): void {
        $uri = $this->totp->provisioningUri(self::RFC_SECRET, 'user@example.com', 'ChreeID');

        $this->assertStringStartsWith('otpauth://totp/ChreeID:user%40example.com?', $uri);
        $this->assertStringContainsString('secret=' . self::RFC_SECRET, $uri);
        $this->assertStringContainsString('issuer=ChreeID', $uri);
    }
}
