<?php
namespace Tests\Feature;

use App\Modules\Credential\Application\EnableMagicLink;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Application\TransferableCredentials;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Application\ClaimTickets;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

// 統合で寄せ元の認証手段をどう引き継ぐか。本人に選ばせ、既定は全部オン
class MergeCredentialTransferTest extends TestCase {
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        RateLimiter::clear('verify');
        RateLimiter::clear('login');
    }

    /**
     * @return OAuthClientModel
     */
    private function client(): OAuthClientModel {
        return OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', 'service-secret'),
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
        ]);
    }

    /**
     * パスワードを持つサービスアカウントと、その入場券を作る。
     *
     * @param OAuthClientModel $client 発行元のサービス
     * @return array{token: string, id: string}
     */
    private function source(OAuthClientModel $client): array {
        $account = app(AuthIdentityRepository::class)
            ->create(AccountOrigin::SERVICE, 'doku@example.com', 'DokuFarm の人');
        app(SetPassword::class)->execute($account->id, 'from-dokufarm');

        ServiceAccountModel::create([
            'client_id' => $client->id,
            'auth_identity_id' => $account->id,
            'service_user_id' => '42',
        ]);

        $ticket = app(ClaimTickets::class)->issue($client, '42');
        $this->assertNotNull($ticket);

        return ['token' => $ticket->token, 'id' => $account->id];
    }

    /**
     * @param bool $withPassword 寄せ先がパスワードを持っているか
     * @return string 認証主体のID (ULID)
     */
    private function signIn(bool $withPassword = true): string {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'me@example.com', '本人');
        app(SetPassword::class)->execute($account->id, 'correct-horse');
        $this->post('/login', ['email' => 'me@example.com', 'password' => 'correct-horse']);

        if (!$withPassword) {
            CredentialModel::query()
                ->where('auth_identity_id', $account->id)
                ->where('type', CredentialType::PASSWORD)
                ->delete();
        }

        return $account->id;
    }

    /**
     * @param string $identityId 認証主体のID
     * @param CredentialType $type 認証方式
     * @return int
     */
    private function credentialCount(string $identityId, CredentialType $type): int {
        return CredentialModel::query()
            ->where('auth_identity_id', $identityId)
            ->where('type', $type)
            ->count();
    }

    // 寄せ先にパスワードがあると上書きになるので、そもそも出さない
    public function test_doesNotOfferAPasswordWhenTheTargetHasOne(): void {
        $client = $this->client();
        $source = $this->source($client);
        $targetId = $this->signIn();

        $offered = app(TransferableCredentials::class)->execute($source['id'], $targetId);

        $this->assertCount(0, $offered);
    }

    // 無ければ出す。これで「DokuFarm のパスワードが使えなくなる」は起きない
    public function test_offersThePasswordWhenTheTargetHasNone(): void {
        $client = $this->client();
        $source = $this->source($client);
        $targetId = $this->signIn(withPassword: false);

        $offered = app(TransferableCredentials::class)->execute($source['id'], $targetId);

        $this->assertCount(1, $offered);
        $this->assertSame(CredentialType::PASSWORD, $offered->first()?->type);
    }

    // パスキーは user handle が認証器側にあるので、移しても照合に落ちる
    public function test_neverOffersAPasskey(): void {
        $client = $this->client();
        $source = $this->source($client);
        CredentialModel::create([
            'auth_identity_id' => $source['id'],
            'type' => CredentialType::PASSKEY,
            'identifier' => 'credential-id',
            'secret' => 'public-key',
        ]);

        $targetId = $this->signIn(withPassword: false);

        $types = app(TransferableCredentials::class)->execute($source['id'], $targetId)
            ->map(fn (CredentialModel $c): CredentialType => $c->type)
            ->all();

        $this->assertNotContains(CredentialType::PASSKEY, $types);
    }

    public function test_movesTheChosenCredential(): void {
        $client = $this->client();
        $source = $this->source($client);
        $targetId = $this->signIn(withPassword: false);

        $offered = app(TransferableCredentials::class)->execute($source['id'], $targetId);

        $this->post('/claim/merge', [
            'token' => $source['token'],
            'credentials' => [$offered->first()?->id],
        ])->assertRedirect('/');

        $this->assertSame(1, $this->credentialCount($targetId, CredentialType::PASSWORD));
        $this->assertSame(0, $this->credentialCount($source['id'], CredentialType::PASSWORD));
    }

    // 外したものは持っていかない
    public function test_leavesUncheckedCredentialsBehind(): void {
        $client = $this->client();
        $source = $this->source($client);
        $targetId = $this->signIn(withPassword: false);

        $this->post('/claim/merge', ['token' => $source['token'], 'credentials' => []])
            ->assertRedirect('/');

        $this->assertSame(0, $this->credentialCount($targetId, CredentialType::PASSWORD));
        $this->assertSame(1, $this->credentialCount($source['id'], CredentialType::PASSWORD));
    }

    // 画面から戻ってきたIDは信用しない。候補に無いものは落とす
    public function test_ignoresCredentialsThatWereNotOffered(): void {
        $client = $this->client();
        $source = $this->source($client);
        $targetId = $this->signIn();

        // 寄せ先がパスワードを持つので、寄せ元のパスワードは候補に出ていない
        $notOffered = CredentialModel::query()
            ->where('auth_identity_id', $source['id'])
            ->where('type', CredentialType::PASSWORD)
            ->firstOrFail();

        $this->post('/claim/merge', ['token' => $source['token'], 'credentials' => [$notOffered->id]])
            ->assertRedirect('/');

        // 寄せ先のパスワードが差し替わっていないこと
        $this->assertSame(1, $this->credentialCount($targetId, CredentialType::PASSWORD));
        $this->assertSame(1, $this->credentialCount($source['id'], CredentialType::PASSWORD));
    }

    // 復旧コードは単独では意味を持たないので、TOTP に追随させる
    public function test_carriesRecoveryCodesWithTheTotp(): void {
        $client = $this->client();
        $source = $this->source($client);

        $secret = app(EnableTotp::class)->generateSecret();
        app(EnableTotp::class)->execute($source['id'], $secret, app(Totp::class)->at($secret, intdiv(time(), 30)));
        CredentialModel::create([
            'auth_identity_id' => $source['id'],
            'type' => CredentialType::RECOVERY_CODE,
            'secret' => hash('sha256', 'code'),
        ]);

        $targetId = $this->signIn(withPassword: false);

        $offered = app(TransferableCredentials::class)->execute($source['id'], $targetId);
        $totp = $offered->firstWhere('type', CredentialType::TOTP);
        $this->assertNotNull($totp);

        // 復旧コードは単独では選ばせない
        $this->assertNull($offered->firstWhere('type', CredentialType::RECOVERY_CODE));

        $this->post('/claim/merge', ['token' => $source['token'], 'credentials' => [$totp->id]])
            ->assertRedirect('/');

        $this->assertSame(1, $this->credentialCount($targetId, CredentialType::RECOVERY_CODE));
    }

    // マジックリンクも1つしか持てないので、寄せ先にあれば出さない
    public function test_doesNotOfferAMagicLinkWhenTheTargetHasOne(): void {
        $client = $this->client();
        $source = $this->source($client);
        app(EnableMagicLink::class)->execute($source['id']);

        $targetId = $this->signIn(withPassword: false);
        app(EnableMagicLink::class)->execute($targetId);

        $types = app(TransferableCredentials::class)->execute($source['id'], $targetId)
            ->map(fn (CredentialModel $c): CredentialType => $c->type)
            ->all();

        $this->assertNotContains(CredentialType::MAGIC_LINK, $types);
    }
}
