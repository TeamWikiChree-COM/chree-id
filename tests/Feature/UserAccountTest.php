<?php
namespace Tests\Feature;

use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Application\ResolveByEmail;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\PendingRegistrationModel;
use App\Modules\Identity\Infrastructure\UserAccountModel;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// origin は出自、UserAccount の有無は現在の統合状態。この2つは別の軸 (KAKUTEI.md)
class UserAccountTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param AccountOrigin $origin 出自
     * @return string 認証主体のID (ULID)
     */
    private function identity(AccountOrigin $origin): string {
        return app(AuthIdentityRepository::class)->create($origin, null, 'テスト')->id;
    }

    public function test_hasNoUserAccountUntilOneIsMade(): void {
        $id = $this->identity(AccountOrigin::SERVICE);

        $this->assertFalse(app(UserAccounts::class)->exists($id));
    }

    // 呼び出し側が何度叩いても増えない
    public function test_isIdempotent(): void {
        $id = $this->identity(AccountOrigin::SERVICE);

        app(UserAccounts::class)->ensure($id);
        app(UserAccounts::class)->ensure($id);

        $this->assertSame(1, UserAccountModel::query()->where('auth_identity_id', $id)->count());
    }

    // 束ねる人格ができても、どういう経緯で作られたかの記録は動かない
    public function test_doesNotTouchTheOrigin(): void {
        $id = $this->identity(AccountOrigin::SERVICE);

        app(UserAccounts::class)->ensure($id);

        $this->assertSame(AccountOrigin::SERVICE, app(AuthIdentityRepository::class)->findById($id)?->origin);
    }

    // 逆も同じ。origin が user でも、束ねていなければ UserAccount は無い
    public function test_originUserDoesNotImplyAUserAccount(): void {
        $id = $this->identity(AccountOrigin::USER);

        $this->assertFalse(app(UserAccounts::class)->exists($id));
    }

    // 本人が作りに来た経路では、最初から束ねる人格を持つ
    public function test_isCreatedWhenSomeoneRegistersThemselves(): void {
        $this->post('/register', ['email' => 'me@example.com'])->assertRedirect('/register/sent');

        $token = Str::random(64);
        PendingRegistrationModel::query()
            ->where('email', 'me@example.com')
            ->update(['token_hash' => hash('sha256', $token)]);

        $this->post('/register/complete', ['token' => $token, 'password' => 'correct-horse'])
            ->assertRedirect();

        $account = app(ResolveByEmail::class)->primary('me@example.com');
        $this->assertNotNull($account);
        $this->assertTrue(app(UserAccounts::class)->exists($account->id));
    }
}
