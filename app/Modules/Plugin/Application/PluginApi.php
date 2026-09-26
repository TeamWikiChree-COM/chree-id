<?php
namespace App\Modules\Plugin\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\LinkedServiceAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Admin\Domain\AdminAccess;
use Illuminate\Support\Collection;

/**
 * プラグインに向けた、本体の窓口。
 *
 * ここに置いたものは本体を変えても壊さないと約束する。プラグインが本体の内部を
 * 直接触ることは止められないが、ここを使えば本体の変更に巻き込まれない。
 * 認証方式のように本体として開きたくないものは、ここに置かない。
 */
class PluginApi {
    private readonly ChreeSession $session;
    private readonly AuthIdentityRepository $accounts;
    private readonly AdminAccess $admin;

    public function __construct(ChreeSession $session, AuthIdentityRepository $accounts, AdminAccess $admin) {
        $this->session = $session;
        $this->accounts = $accounts;
        $this->admin = $admin;
    }

    /**
     * @return string|null ログイン中のアカウントID (ULID)。未ログインなら null
     */
    public function accountId(): ?string {
        return $this->session->accountId();
    }

    /**
     * @return bool ログイン中のアカウントが運営か
     */
    public function isAdmin(): bool {
        $accountId = $this->session->accountId();

        return $accountId !== null && $this->admin->allows($this->accounts->findById($accountId));
    }

    /**
     * そのサービスでの、この利用者のサービスアカウント。
     *
     * サービスへ問い合わせるときの手がかりになる。サービスが知っているのは
     * 自分の識別子 (serviceUserId) か、OIDC で渡した sub のどちらか。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $clientId サービスの client_id
     * @return list<array{sub: string|null, serviceUserId: string|null}>
     */
    public function serviceAccounts(string $accountId, string $clientId): array {
        $rows = ServiceAccountModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('client_id', $clientId)
            ->orderBy('created_at')
            ->get()
            ->toBase();

        return array_values(LinkedServiceAccounts::prefer($rows)
            ->map(static fn (ServiceAccountModel $row): array => ['sub' => $row->sub, 'serviceUserId' => $row->service_user_id])
            ->all());
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<string> 連携しているサービスの client_id
     */
    public function connectedClientIds(string $accountId): array {
        /** @var Collection<int, string> $ids */
        $ids = ServiceAccountModel::query()->where('auth_identity_id', $accountId)->distinct()->pluck('client_id');

        return array_values($ids->all());
    }
}
