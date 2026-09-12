<?php
namespace App\Modules\Identity\Http;

use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Application\WithdrawAccount;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\ListConnectedServices;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ChreeID の退会。
 *
 * 取り返しがつかない操作なので、設定画面には混ぜず専用の画面に分けている。
 * 何を失うのかを見せてから確かめる。
 */
class WithdrawalController {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ChreeSession $session,
        private readonly WithdrawAccount $withdraw,
        private readonly ListConnectedServices $services,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @return Response|RedirectResponse
     */
    public function show(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        $account = $this->accounts->findById($accountId);
        if ($account === null) return LoginRedirect::guest();

        return Inertia::render('Settings/Withdraw', [
            'email' => $account->email,
            // 何を巻き添えにするのかを見せてから確かめる
            'services' => $this->services->execute($accountId),
            'graceDays' => PurgeDeletedAccounts::graceDays(),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return LoginRedirect::guest();

        // 画面の説明を読み飛ばして押せてしまわないよう、明示の同意を要る形にする
        $request->validate(['understood' => ['accepted']]);

        if (!$this->withdraw->execute($accountId)) return redirect('/settings');

        // 行は猶予のあいだ残るので、記録も一緒に残る
        $this->audit->record(AuditAction::ACCOUNT_WITHDRAWN, $accountId);

        $this->session->logout();

        return redirect('/login')->with('withdrawn', true);
    }
}
