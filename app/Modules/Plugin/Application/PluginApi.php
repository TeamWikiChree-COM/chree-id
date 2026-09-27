<?php
namespace App\Modules\Plugin\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Linking\Application\LinkedServiceAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Admin\Domain\AdminAccess;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Http\ExternalLoginLanding;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

/**
 * プラグインに向けた、本体の窓口。
 *
 * ここに置いたメソッドは、本体を変えても互換性を保つ。プラグインが本体の内部を
 * 直接触ることは止められないが、ここを使えば本体の変更に巻き込まれない。
 * 認証方式のように本体として開きたくないものは、ここに置かない。
 *
 * @api
 */
class PluginApi {
    private readonly ChreeSession $session;
    private readonly AuthIdentityRepository $accounts;
    private readonly AdminAccess $admin;
    private readonly ExternalLoginLanding $landing;

    public function __construct(ChreeSession $session, AuthIdentityRepository $accounts, AdminAccess $admin, ExternalLoginLanding $landing) {
        $this->session = $session;
        $this->accounts = $accounts;
        $this->admin = $admin;
        $this->landing = $landing;
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

    /**
     * PluginHooks::addExternalIdp() で足した IdP から戻ってきた応答を受け、ログインか連携を済ませる。
     *
     * state の照合、アカウントの紐付け、停止の確認、セッションは本体が受け持つ。
     * プラグインは応答の検証だけを $verify に書く。
     *
     * @param string $provider ExternalIdp::name() の値
     * @param string $state 戻ってきた state (SAML なら RelayState)
     * @param Closure(string): ExternalIdentity $verify 発行時の nonce を受け取り、応答を確かめて外部アカウントを返す。
     *   確かめられなければ RuntimeException を投げる
     * @return RedirectResponse
     */
    public function finishExternalLogin(string $provider, string $state, Closure $verify): RedirectResponse {
        return $this->landing->finish($provider, $state, $verify);
    }

    /**
     * 利用者が IdP で取りやめたなど、検証するまでもなく失敗した応答を受けたとき。
     *
     * @param string $message 画面に出す文言
     * @return RedirectResponse
     */
    public function abortExternalLogin(string $message): RedirectResponse {
        return $this->landing->abort($message);
    }
}
