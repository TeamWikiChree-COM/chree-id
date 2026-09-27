<?php
namespace App\Modules\Plugin\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Linking\Application\LinkedServiceAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Admin\Domain\AdminAccess;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Http\ExternalLoginLanding;
use App\Modules\Provider\Application\ResolveSignInGrant;
use App\Modules\Provider\Application\SignedInService;
use App\Modules\Provider\Infrastructure\PendingSignIns;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * プラグインに向けた、本体の窓口。
 *
 * ここに置いたメソッドは、本体を変えても互換性を保つ。プラグインが本体の内部を
 * 直接触ることは止められないが、ここを使えば本体の変更に巻き込まれない。
 *
 * @api
 */
class PluginApi {
    private readonly ChreeSession $session;
    private readonly AuthIdentityRepository $accounts;
    private readonly AdminAccess $admin;
    private readonly ExternalLoginLanding $landing;
    private readonly PendingSignIns $signIns;
    private readonly ResolveSignInGrant $grants;

    public function __construct(
        ChreeSession $session,
        AuthIdentityRepository $accounts,
        AdminAccess $admin,
        ExternalLoginLanding $landing,
        PendingSignIns $signIns,
        ResolveSignInGrant $grants,
    ) {
        $this->session = $session;
        $this->accounts = $accounts;
        $this->admin = $admin;
        $this->landing = $landing;
        $this->signIns = $signIns;
        $this->grants = $grants;
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
     * Facades\ExternalIdpRegistry::register() で追加した IdP から戻ってきた応答を受け、ログインか連携を済ませる。
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

    /**
     * サービスへのサインインを本体に頼む。ChreeID が IdP として別の方式 (SAML など) で答えるとき用。
     *
     * ログイン、引き取り前の確認、同意、サービスアカウントの選択は OIDC と同じ手順で本体が進める。
     * 済んだら `$returnUrl?grant=…` へ戻すので、takeServiceSignIn() で結果を受け取る。
     *
     * @param string $clientId 接続先サービスの client_id
     * @param list<string> $scopes 渡す属性の範囲 (openid、email、profile など)。サービスに許した範囲の中だけ
     * @param string $returnUrl 済んだら戻る先。プラグインのルート (/plugins/…) に限る
     * @return RedirectResponse
     * @throws InvalidArgumentException サービスが無い、範囲を許していない、戻り先がプラグインの外の場合
     */
    public function authorizeService(string $clientId, array $scopes, string $returnUrl): RedirectResponse {
        $client = OAuthClientModel::query()->find($clientId);
        if ($client === null) throw new InvalidArgumentException("unknown client: {$clientId}");
        if (!$client->allowsScopes($scopes)) throw new InvalidArgumentException("scopes not allowed for {$clientId}");

        // 戻り先を自由にすると、引換券を外へ持ち出させるオープンリダイレクタになる
        if (!str_starts_with($returnUrl, url('/plugins/'))) throw new InvalidArgumentException("return url outside plugins: {$returnUrl}");

        $pending = $this->signIns->start($clientId, $scopes, $returnUrl);

        return redirect("/authorize/pending/{$pending->id}");
    }

    /**
     * authorizeService() の戻りで受け取った引換券を、サービスに渡す sub と属性にする。
     *
     * 引換券は一度しか使えず、始めたのと同じブラウザでしか使えない。
     *
     * @param string $grant 戻り先に付いてきた grant の値
     * @return SignedInService|null 使えない引換券、または済んだ後に停止された場合は null
     */
    public function takeServiceSignIn(string $grant): ?SignedInService {
        $taken = $this->signIns->takeGrant($grant);

        return $taken === null ? null : $this->grants->execute($taken);
    }
}
