<?php
namespace Tests\Unit;

use App\Modules\Identity\Domain\PlusAddress;
use PHPUnit\Framework\TestCase;

// 「+」付きアドレスを同じ受信箱とみなすのは、対応が分かっているドメインだけ
class PlusAddressTest extends TestCase {
    private PlusAddress $plus;

    #[\Override]
    protected function setUp(): void {
        $this->plus = new PlusAddress(['gmail.com']);
    }

    public function test_stripsTagOnSupportedDomain(): void {
        $this->assertSame('aaa@gmail.com', $this->plus->base('aaa+bbb@gmail.com'));
        $this->assertTrue($this->plus->sameInbox('AAA+modparks@Gmail.com', 'aaa@gmail.com'));
    }

    // 対応していないドメインでは aaa+bbb が別人の受信箱になり得る
    public function test_keepsTagOnUnknownDomain(): void {
        $this->assertSame('aaa+bbb@example.com', $this->plus->base('aaa+bbb@example.com'));
        $this->assertFalse($this->plus->sameInbox('aaa+bbb@example.com', 'aaa@example.com'));
    }

    public function test_differentLocalPartIsDifferentInbox(): void {
        $this->assertFalse($this->plus->sameInbox('aab+x@gmail.com', 'aaa@gmail.com'));
    }
}
