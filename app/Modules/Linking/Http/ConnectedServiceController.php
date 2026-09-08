<?php
namespace App\Modules\Linking\Http;

use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\RevokeServiceAccess;
use Illuminate\Http\RedirectResponse;

/**
 * 利用者自身が、連携しているサービスを切る。
 *
 * 管理画面の「接続サービス」(サービスそのものの登録) とは別物。
 * こちらは自分のアカウントとサービスの結び付きだけを扱う。
 */
class ConnectedServiceController {
    public function __construct(
        private readonly ChreeSession $session,
        private readonly RevokeServiceAccess $revoke,
    ) {}

    /**
     * @param string $client サービスの client_id
     * @return RedirectResponse
     */
    public function destroy(string $client): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $this->revoke->execute($accountId, $client);

        return redirect('/')->with('serviceRevoked', true);
    }
}
