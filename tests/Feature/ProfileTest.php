<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

// プロフィールの編集。登録時に表示名を任意にしたので、ここが設定手段になる
class ProfileTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
    }

    /**
     * @param string|null $displayName 最初の表示名
     * @return string アカウントID (ULID)
     */
    private function login(?string $displayName = null): string {
        $account = app(ChreeAccountRepository::class)
            ->create(AccountOrigin::USER, 'user@example.com', $displayName);
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        return $account->id;
    }

    public function test_requiresLogin(): void {
        $this->get('/profile')->assertRedirect('/login');
        $this->post('/profile', ['display_name' => 'なまえ'])->assertRedirect('/login');
    }

    public function test_showsProfile(): void {
        $this->login('もとの名前');

        $this->get('/profile')->assertOk();
    }

    public function test_setsDisplayNameWhenUnset(): void {
        $id = $this->login();

        $this->post('/profile', ['display_name' => 'あたらしい名前'])->assertRedirect('/profile');

        $this->assertSame('あたらしい名前', app(ChreeAccountRepository::class)->findById($id)?->displayName);
    }

    public function test_changesDisplayName(): void {
        $id = $this->login('もとの名前');

        $this->post('/profile', ['display_name' => 'あたらしい名前']);

        $this->assertSame('あたらしい名前', app(ChreeAccountRepository::class)->findById($id)?->displayName);
    }

    public function test_clearingReturnsToUnset(): void {
        $id = $this->login('もとの名前');

        $this->post('/profile', ['display_name' => '   ']);

        $this->assertNull(app(ChreeAccountRepository::class)->findById($id)?->displayName);
    }

    public function test_rejectsTooLongDisplayName(): void {
        $this->login();

        $this->post('/profile', ['display_name' => str_repeat('あ', 101)])
            ->assertSessionHasErrors('display_name');
    }

    // 他人の表示名を書き換えられてはいけない
    public function test_onlyChangesOwnAccount(): void {
        $other = app(ChreeAccountRepository::class)
            ->create(AccountOrigin::USER, 'other@example.com', 'ほかの人');
        $this->login('もとの名前');

        $this->post('/profile', ['display_name' => 'あたらしい名前']);

        $this->assertSame('ほかの人', app(ChreeAccountRepository::class)->findById($other->id)?->displayName);
    }
}
