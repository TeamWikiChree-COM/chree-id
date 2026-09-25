<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\MergeAccounts;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AccountEmailModel;
use App\Modules\Identity\Mail\VerifyAccountEmailMail;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Infrastructure\Claims\EmailClaims;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// 追加のメールアドレスと、サービスごとに渡すアドレスの割り当て
class AccountEmailTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('login');
        Mail::fake();
    }

    /**
     * @param string $email 主アドレス
     * @return string アカウントID (ULID)
     */
    private function login(string $email = 'aaa@gmail.com'): string {
        $repo = app(AuthIdentityRepository::class);
        $account = $repo->create(AccountOrigin::USER, $email);
        $repo->markEmailVerified($account->id);
        app(UserAccounts::class)->ensure($account->id);
        app(SetPassword::class)->execute($account->id, 'correct-horse');

        $this->post('/login', ['email' => $email, 'password' => 'correct-horse']);

        return $account->id;
    }

    /**
     * @param string $accountId 持ち主のアカウントID (ULID)
     * @return ServiceAccountModel
     */
    private function link(string $accountId): ServiceAccountModel {
        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'name' => 'ModParks',
            'redirect_uris' => ['https://mp.example.com/callback'],
            'scopes' => 'openid email',
            'is_confidential' => false,
            'trust' => ServiceTrust::OFFICIAL,
        ]);

        return ServiceAccountModel::create(['client_id' => $client->id, 'auth_identity_id' => $accountId]);
    }

    /**
     * @param string $email 追加して確認まで済ませるアドレス
     * @return void
     */
    private function addVerified(string $email): void {
        $this->post('/profile/emails', ['email' => $email])->assertSessionHasNoErrors();

        $url = null;
        Mail::assertSent(VerifyAccountEmailMail::class, function (VerifyAccountEmailMail $mail) use (&$url, $email): bool {
            if (!$mail->hasTo($email)) return false;
            $url = $mail->verifyUrl;

            return true;
        });

        $this->get((string) parse_url((string) $url, PHP_URL_PATH));
    }

    public function test_unverifiedAddressCannotBeAssigned(): void {
        $id = $this->login();
        $link = $this->link($id);

        $this->post('/profile/emails', ['email' => 'other@example.com']);
        $this->post("/services/{$link->id}/email", ['email' => 'other@example.com'])->assertSessionHasErrors('email');

        $this->assertNull($link->refresh()->email);
    }

    public function test_verifiedAddressIsSentToService(): void {
        $id = $this->login();
        $link = $this->link($id);
        $this->addVerified('other@example.com');

        $this->post("/services/{$link->id}/email", ['email' => 'other@example.com'])->assertSessionHasNoErrors();

        $claims = app(EmailClaims::class)->resolve(app(AuthIdentityRepository::class)->findById($id), $link->id);
        $this->assertSame(['email' => 'other@example.com', 'email_verified' => true], $claims);
    }

    public function test_plusVariantAllowedOnlyOnKnownDomains(): void {
        $id = $this->login();
        $link = $this->link($id);
        $this->addVerified('me@example.com');

        $this->post("/services/{$link->id}/email", ['email' => 'aaa+modparks@gmail.com'])->assertSessionHasNoErrors();
        $this->assertSame('aaa+modparks@gmail.com', $link->refresh()->email);

        // example.com では aaa+bbb が別人の受信箱かもしれない
        $this->post("/services/{$link->id}/email", ['email' => 'me+x@example.com'])->assertSessionHasErrors('email');
    }

    public function test_removingAddressResetsAssignment(): void {
        $id = $this->login();
        $link = $this->link($id);
        $this->addVerified('other@example.com');
        $this->post("/services/{$link->id}/email", ['email' => 'other@example.com']);

        $row = AccountEmailModel::query()->where('email', 'other@example.com')->firstOrFail();
        $this->post('/profile/emails/remove', ['id' => $row->id]);

        $this->assertNull($link->refresh()->email);
    }

    public function test_promoteSwapsWithPrimary(): void {
        $id = $this->login();
        $this->addVerified('other@example.com');
        $row = AccountEmailModel::query()->where('email', 'other@example.com')->firstOrFail();

        $this->post('/profile/emails/primary', ['id' => $row->id])->assertSessionHasNoErrors();

        $this->assertSame('other@example.com', app(AuthIdentityRepository::class)->findById($id)?->email);
        $this->assertTrue(AccountEmailModel::query()->where('email', 'aaa@gmail.com')->whereNotNull('verified_at')->exists());
    }

    // 追加アドレスではログインできない
    public function test_additionalAddressCannotLogIn(): void {
        $this->login();
        $this->addVerified('other@example.com');
        $this->post('/logout');

        $this->post('/login', ['email' => 'other@example.com', 'password' => 'correct-horse']);

        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_cannotAssignToSomeoneElsesService(): void {
        $this->login();
        $other = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'x@example.com');
        $link = $this->link($other->id);

        $this->post("/services/{$link->id}/email", ['email' => 'aaa@gmail.com'])->assertSessionHasErrors('email');
    }

    public function test_connectedServicePageShowsOwnServiceOnly(): void {
        $id = $this->login();
        $own = $this->link($id);
        $other = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'x@example.com');
        $theirs = $this->link($other->id);

        $this->get("/connected/{$own->id}")->assertOk();
        $this->get("/connected/{$theirs->id}")->assertNotFound();
    }

    public function test_mergeCarriesAddresses(): void {
        $target = $this->login();
        $repo = app(AuthIdentityRepository::class);
        $source = $repo->create(AccountOrigin::SERVICE, 'old@example.com');
        $repo->markEmailVerified($source->id);

        app(MergeAccounts::class)->execute($source->id, $target);

        $this->assertTrue(AccountEmailModel::query()
            ->where('auth_identity_id', $target)
            ->where('email', 'old@example.com')
            ->whereNotNull('verified_at')
            ->exists());
    }
}
