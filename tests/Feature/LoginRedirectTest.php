<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * ログインを挟んだときの戻り先。
 *
 * URL を直接開いた人を送りっぱなしにすると、ブックマークや共有リンクから
 * 来た人が毎回ダッシュボードに落ちて、自分で辿り直すことになる。
 */
class LoginRedirectTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
    }

    /**
     * @return string 作ったアカウントのID
     */
    private function makeAccount(): string {
        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        $accounts->markEmailVerified($account->id);

        return $account->id;
    }

    /**
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\RedirectResponse>
     */
    private function login(): \Illuminate\Testing\TestResponse {
        return $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);
    }

    public function test_comesBackToThePageThatWasAskedFor(): void {
        $this->makeAccount();

        $this->get('/settings/security')->assertRedirect('/login');
        $this->login()->assertRedirect('/settings/security');
    }

    // クエリを落とすと、一覧の絞り込みなどが消えて「戻れた」ことにならない
    public function test_keepsTheQueryString(): void {
        $this->makeAccount();

        $this->get('/settings/devices?sort=recent');
        $this->login()->assertRedirect('/settings/devices?sort=recent');
    }

    public function test_goesToTheTopWhenNothingWasAskedFor(): void {
        $this->makeAccount();

        $this->login()->assertRedirect('/');
    }

    /**
     * **POST の戻り先は覚えない。**
     *
     * Laravel の `redirect()->guest()` は GET 以外のとき Referer を覚えるが、
     * Referer は相手が好きな値を入れられるので、ログイン直後に外部へ飛ばせてしまう。
     */
    public function test_doesNotRememberWhereAPostCameFrom(): void {
        $this->makeAccount();

        $this->post('/security/magic-link', [], ['referer' => 'https://evil.example.com/']);

        $this->login()->assertRedirect('/');
    }

    // 出口でも確かめる。覚える経路が増えたときに素通りさせないため
    public function test_refusesAnIntendedUrlOnAnotherHost(): void {
        $this->makeAccount();

        $this->withSession(['url.intended' => 'https://evil.example.com/steal'])
            ->login()
            ->assertRedirect('/');
    }
}
