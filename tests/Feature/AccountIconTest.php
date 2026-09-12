<?php
namespace Tests\Feature;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Domain\IconSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// アカウントのアイコン
class AccountIconTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param string|null $email 連絡先。Gravatar を選べるかに関わる
     * @return string アカウントID (ULID)
     */
    private function login(?string $email = 'user@example.com'): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, $email, 'テスト');
        $this->withSession(['chreeid.account_id' => $account->id]);

        return $account->id;
    }

    public function test_requiresLogin(): void {
        $this->post('/profile/icon', ['source' => 'none'])->assertRedirect('/login');
    }

    public function test_usesGravatar(): void {
        $accountId = $this->login();

        $this->post('/profile/icon', ['source' => 'gravatar'])->assertRedirect('/settings');

        $account = app(AuthIdentityRepository::class)->findById($accountId);
        $this->assertNotNull($account);
        $this->assertSame(IconSource::GRAVATAR, $account->iconSource);
    }

    /**
     * メールアドレスが無いと Gravatar は引けない
     */
    public function test_rejectsGravatarWithoutEmail(): void {
        $this->login(email: null);

        $this->post('/profile/icon', ['source' => 'gravatar'])->assertSessionHasErrors('source');
    }

    public function test_uploadsImage(): void {
        Storage::fake('local');
        $accountId = $this->login();

        $this->post('/profile/icon', [
            'source' => 'upload',
            'icon' => UploadedFile::fake()->image('me.png'),
        ])->assertRedirect('/settings');

        $account = app(AuthIdentityRepository::class)->findById($accountId);
        $this->assertNotNull($account);
        $this->assertSame(IconSource::UPLOAD, $account->iconSource);
        $this->assertIsString($account->iconPath);
        Storage::disk('local')->assertExists($account->iconPath);
    }

    public function test_rejectsUploadWithoutFile(): void {
        $this->login();

        $this->post('/profile/icon', ['source' => 'upload'])->assertSessionHasErrors('icon');
    }

    public function test_clearsIcon(): void {
        Storage::fake('local');
        $accountId = $this->login();
        $this->post('/profile/icon', ['source' => 'upload', 'icon' => UploadedFile::fake()->image('me.png')]);

        $before = app(AuthIdentityRepository::class)->findById($accountId);
        $this->assertNotNull($before);
        $this->assertIsString($before->iconPath);

        $this->post('/profile/icon', ['source' => 'none'])->assertRedirect('/settings');

        $account = app(AuthIdentityRepository::class)->findById($accountId);
        $this->assertNotNull($account);
        $this->assertSame(IconSource::NONE, $account->iconSource);
        // 出どころを変えたら画像も残さない
        Storage::disk('local')->assertMissing($before->iconPath);
    }

    public function test_servesUploadedImage(): void {
        Storage::fake('local');
        $accountId = $this->login();
        $this->post('/profile/icon', ['source' => 'upload', 'icon' => UploadedFile::fake()->image('me.png')]);

        $this->get("/profile/icon/{$accountId}")->assertOk();
    }

    public function test_returnsNotFoundWhenNoUpload(): void {
        $accountId = $this->login();

        $this->get("/profile/icon/{$accountId}")->assertNotFound();
    }
}
