<?php
namespace Tests\Feature;

use App\Modules\Client\Domain\ServiceTrust;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

// 未ログインで /oauth/authorize に来た人が、ログインのあと認可の続きへ戻れるか
class AuthorizeLoginReturnTest extends TestCase {
    use RefreshDatabase;

    #[TestDox('パスワードでログインしたら、/oauth/authorize の続きへ戻す')]
    public function test_returnsAfterPasswordLogin(): void {
        OAuthClientModel::create([
            'id' => 'rp',
            'name' => 'RP',
            'redirect_uris' => ['https://rp.example.com/cb'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'skips_consent' => true,
        ]);
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        app(UserAccounts::class)->ensure($account->id);

        $authorize = '/oauth/authorize?' . http_build_query([
            'client_id' => 'rp',
            'redirect_uri' => 'https://rp.example.com/cb',
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => 's',
        ]);
        $this->get($authorize)->assertRedirect('/login');
        $this->get('/login')->assertOk();

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])->assertRedirect(url($authorize));
    }

    /**
     * ログインのフォームは Inertia の XHR で送られる。302 で /oauth/authorize へ戻すと、XHR がそれをたどり、
     * その先の RP (別オリジン) へのリダイレクトを CORS でたどれずに止まる。ブラウザごと移る 409 で返す必要がある
     */
    #[TestDox('Inertia のフォームから入っても、ブラウザごと /oauth/authorize の続きへ移す')]
    public function test_returnsWithFullNavigationFromInertia(): void {
        $this->makeClientAndAccount();
        $authorize = $this->authorizeUrl();
        $this->get($authorize)->assertRedirect('/login');

        $this->withHeaders(['X-Inertia' => 'true'])
            ->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url($authorize));
    }

    #[TestDox('ログイン中にログイン画面を開いたら、画面を出さずにダッシュボードへ')]
    public function test_loggedInSkipsLoginPage(): void {
        $this->makeClientAndAccount();
        $this->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse']);

        $this->get('/login')->assertRedirect('/');
    }

    #[TestDox('戻り先が無ければ、今までどおりダッシュボードへ')]
    public function test_fallsBackToDashboard(): void {
        $this->makeClientAndAccount();

        $this->withHeaders(['X-Inertia' => 'true'])
            ->post('/login', ['email' => 'user@example.com', 'password' => 'correct-horse'])
            ->assertRedirect('/');
    }

    private function makeClientAndAccount(): void {
        OAuthClientModel::create([
            'id' => 'rp',
            'name' => 'RP',
            'redirect_uris' => ['https://rp.example.com/cb'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'skips_consent' => true,
        ]);
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', 'テスト');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        app(UserAccounts::class)->ensure($account->id);
    }

    /**
     * @return string
     */
    private function authorizeUrl(): string {
        return '/oauth/authorize?' . http_build_query([
            'client_id' => 'rp',
            'redirect_uri' => 'https://rp.example.com/cb',
            'response_type' => 'code',
            'scope' => 'openid',
            'state' => 's',
        ]);
    }
}
