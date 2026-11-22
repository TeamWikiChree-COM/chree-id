<?php
namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\PurgeDeletedAccounts;
use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Application\WithdrawAccount;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Linking\Application\CheckServiceDeletion;
use App\Modules\Linking\Application\SplitException;
use App\Modules\Linking\Application\WithdrawalServices;
use App\Modules\Linking\Http\SplitCredentialOptions;
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
 * 連携サービスごとに、削除を依頼するか解放するかを本人に選ばせてから確かめる。
 */
class WithdrawalController extends Controller {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ChreeSession $session,
        private readonly WithdrawAccount $withdraw,
        private readonly WithdrawalServices $services,
        private readonly SplitCredentialOptions $credentialOptions,
        private readonly CheckServiceDeletion $check,
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

        $services = $this->services->execute($accountId);
        $deletable = array_column(array_filter($services, static fn (array $service): bool => $service['deletable']), 'id');

        return Inertia::render('Settings/Withdraw', [
            'email' => $account->email,
            'services' => $services,
            'credentials' => $this->credentialOptions->of($accountId),
            // サービスへの問い合わせは遅れうるので、画面を先に出す
            'checks' => Inertia::defer(fn (): array => $deletable === [] ? [] : $this->check->execute($accountId, $deletable)),
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

        $credentialIds = array_column($this->credentialOptions->of($accountId), 'id');
        $choices = WithdrawalChoicesInput::read($request, $this->services->execute($accountId), $credentialIds);

        try {
            if (!$this->withdraw->execute($accountId, $choices)) return redirect('/settings');
        } catch (SplitException $e) {
            throw ValidationException::withMessages(['services' => __('linking.split.' . $e->reason)]);
        }

        // 行は猶予のあいだ残るので、記録も一緒に残る
        $this->audit->record(AuditAction::ACCOUNT_WITHDRAWN, $accountId, [
            'deleted' => $choices->deletions,
            'released' => array_keys($choices->releases),
        ]);

        $this->session->logout();

        return redirect('/login')->with('withdrawn', true);
    }
}
