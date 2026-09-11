<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\SuggestMergeCandidates;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

// 統合候補の提示。挙げるだけで、勝手には統合しない (ARCHITECTURE 8.7)
class MergeCandidateTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
    }

    /**
     * @param string|null $email 連絡先
     * @param string $name 表示名
     * @return string 認証主体のID (ULID)
     */
    private function identity(?string $email, string $name = 'テスト'): string {
        return app(AuthIdentityRepository::class)->create(AccountOrigin::SERVICE, $email, $name)->id;
    }

    /**
     * @return string ログインした認証主体のID (ULID)
     */
    private function signIn(string $email = 'me@example.com'): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, $email, '本人');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        app(UserAccounts::class)->ensure($account->id);

        $this->post('/login', ['email' => $email, 'password' => 'correct-horse']);

        return $account->id;
    }

    public function test_suggestsAnotherAccountWithTheSameAddress(): void {
        $mine = $this->signIn();
        $other = $this->identity('me@example.com', 'DokuFarm の私');

        $candidates = app(SuggestMergeCandidates::class)->execute($mine);

        $this->assertCount(1, $candidates);
        $this->assertSame($other, $candidates[0]['id']);
        $this->assertFalse($candidates[0]['hasUserAccount']);
    }

    public function test_doesNotSuggestItself(): void {
        $mine = $this->signIn();

        $this->assertSame([], app(SuggestMergeCandidates::class)->execute($mine));
    }

    // アドレスが違えば手がかりが無い
    public function test_doesNotSuggestADifferentAddress(): void {
        $mine = $this->signIn();
        $this->identity('someone@example.com');

        $this->assertSame([], app(SuggestMergeCandidates::class)->execute($mine));
    }

    // アドレスを持たない人は 26% いる。手がかりが無いので挙げようがない
    public function test_suggestsNothingWithoutAnAddress(): void {
        $mine = $this->identity(null);

        $this->assertSame([], app(SuggestMergeCandidates::class)->execute($mine));
    }

    // 停止済みは挙げない
    public function test_doesNotSuggestASuspendedAccount(): void {
        $mine = $this->signIn();
        $other = $this->identity('me@example.com');
        app(AuthIdentityRepository::class)->suspend($other);

        $this->assertSame([], app(SuggestMergeCandidates::class)->execute($mine));
    }

    public function test_showsCandidatesOnTheDashboard(): void {
        $this->signIn();
        $this->identity('me@example.com', 'DokuFarm の私');

        $this->get('/')->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard')
            ->has('mergeCandidates', 1));
    }

    // 挙げただけでは何も起きない。統合は本人の操作を通す
    public function test_doesNotMergeOnItsOwn(): void {
        $mine = $this->signIn();
        $other = $this->identity('me@example.com');

        $this->get('/');

        $this->assertNotNull(app(AuthIdentityRepository::class)->findById($other));
        $this->assertFalse(app(UserAccounts::class)->exists($other));
        $this->assertNotSame($mine, $other);
    }
}
