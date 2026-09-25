<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Infrastructure\AccountEmailModel;

/**
 * 追加のメールアドレスの一覧と削除。
 */
class AccountEmails {
    private readonly ServiceEmails $serviceEmails;

    public function __construct(ServiceEmails $serviceEmails) {
        $this->serviceEmails = $serviceEmails;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{id: string, email: string, verified: bool, pendingUntil: string|null}>
     */
    public function list(string $accountId): array {
        return array_values(AccountEmailModel::query()
            ->where('auth_identity_id', $accountId)
            ->orderBy('created_at')
            ->get()
            ->map(static fn (AccountEmailModel $row): array => [
                'id' => $row->id,
                'email' => $row->email,
                'verified' => $row->isVerified(),
                'pendingUntil' => $row->token_expires_at?->isFuture() === true
                    ? $row->token_expires_at->toDateTimeString()
                    : null,
            ])
            ->all());
    }

    /**
     * 消したアドレスを割り当てていたサービスは主アドレスに戻る。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $emailId 追加アドレスのID (ULID)
     * @return string 消したアドレス
     * @throws AccountEmailException
     */
    public function remove(string $accountId, string $emailId): string {
        $row = AccountEmailModel::query()->where('auth_identity_id', $accountId)->find($emailId);
        if ($row === null) throw new AccountEmailException(AccountEmailException::NOT_FOUND);

        $row->delete();
        $this->serviceEmails->prune($accountId);

        return $row->email;
    }
}
