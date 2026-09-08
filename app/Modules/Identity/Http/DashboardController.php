<?php
namespace App\Modules\Identity\Http;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\ListConnectedServices;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ログイン後のトップ。今の状態を確かめるための画面
 */
class DashboardController {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly ChreeSession $session,
        private readonly ListConnectedServices $services,
    ) {}

    /**
     * @return Response|RedirectResponse
     */
    public function __invoke(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $account = $this->accounts->findById($accountId);
        if ($account === null) return redirect('/login');

        return Inertia::render('Dashboard', [
            'account' => [
                'id' => $account->id,
                'email' => $account->email,
                'displayName' => $account->displayName,
                'origin' => $account->origin->value,
                'emailVerified' => $account->isEmailVerified(),
            ],
            'credentials' => $this->credentialsOf($accountId),
            'services' => $this->services->execute($accountId),
        ]);
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{type: string, label: string|null, lastUsedAt: string|null}>
     */
    private function credentialsOf(string $accountId): array {
        $rows = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->orderBy('type')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            // 復旧コードは1本1行なので、一覧にそのまま並べても意味がない
            if ($row->type === CredentialType::RECOVERY_CODE) continue;

            $data = $row->data;
            $label = is_array($data) && is_string($data['label'] ?? null) ? $data['label'] : null;

            $result[] = [
                'type' => $row->type->value,
                'label' => $label,
                'lastUsedAt' => $row->last_used_at?->toDateTimeString(),
            ];
        }

        return $result;
    }
}
