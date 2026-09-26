<?php
namespace App\Modules\Linking\Http;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\ServiceEmails;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Linking\Application\ListConnectedServices;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 連携しているサービス1件の画面。
 *
 * 渡すメールアドレスの選択・サービス側の設定・分離・ログアウトをここにまとめる。
 * 一覧の行に並べると、押し間違えやすいうえ狭い画面で行の中身が潰れるため。
 */
class ConnectedServicePageController extends Controller {
    private readonly ChreeSession $session;
    private readonly AuthIdentityRepository $accounts;
    private readonly ListConnectedServices $services;
    private readonly ServiceEmails $serviceEmails;
    private readonly UserAccounts $userAccounts;

    public function __construct(
        ChreeSession $session,
        AuthIdentityRepository $accounts,
        ListConnectedServices $services,
        ServiceEmails $serviceEmails,
        UserAccounts $userAccounts,
    ) {
        $this->session = $session;
        $this->accounts = $accounts;
        $this->services = $services;
        $this->serviceEmails = $serviceEmails;
        $this->userAccounts = $userAccounts;
    }

    /**
     * @param string $serviceAccount サービスアカウントのID (ULID)
     * @return Response|RedirectResponse
     */
    public function show(string $serviceAccount): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        // 他人のサービスアカウントを指されても、自分の一覧に無ければ見つからない扱いにする
        $service = collect($this->services->execute($accountId))->firstWhere('id', $serviceAccount);
        if ($service === null) abort(404);

        return Inertia::render('Services/Connected', [
            'service' => $service,
            'primaryEmail' => $this->accounts->findById($accountId)?->email,
            // サービスアカウントは追加アドレスを持たないので選ばせない
            'emailOptions' => $this->userAccounts->exists($accountId) ? $this->serviceEmails->options($accountId) : [],
        ]);
    }
}
