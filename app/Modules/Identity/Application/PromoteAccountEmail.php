<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AccountEmailModel;
use App\Modules\Identity\Mail\EmailChangeNoticeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * 確認済みの追加アドレスを主アドレスにする。
 *
 * 主アドレスと入れ替える。今までの主アドレスは追加アドレスとして残るので、
 * それを割り当てていたサービスもそのまま使える。
 * 確認済みのアドレスへ移るだけなので、改めて確認メールは送らない。
 */
class PromoteAccountEmail {
    private readonly AuthIdentityRepository $accounts;
    private readonly ResolveByEmail $byEmail;

    public function __construct(AuthIdentityRepository $accounts, ResolveByEmail $byEmail) {
        $this->accounts = $accounts;
        $this->byEmail = $byEmail;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $emailId 追加アドレスのID (ULID)
     * @return string 新しい主アドレス
     * @throws AccountEmailException
     */
    public function execute(string $accountId, string $emailId): string {
        $account = $this->accounts->findById($accountId);
        $row = AccountEmailModel::query()->where('auth_identity_id', $accountId)->find($emailId);
        if ($account === null || $row === null) throw new AccountEmailException(AccountEmailException::NOT_FOUND);
        if (!$row->isVerified()) throw new AccountEmailException(AccountEmailException::NOT_VERIFIED);

        // 主アドレスはログインとパスワード再設定に使う。他人と重なると本人を決められなくなる
        $existing = $this->byEmail->primary($row->email);
        if ($existing !== null && $existing->id !== $accountId) throw new AccountEmailException(AccountEmailException::TAKEN);

        $old = $account->email;
        $newEmail = $row->email;

        DB::transaction(function () use ($accountId, $row, $old, $account, $newEmail): void {
            if ($old === null) $row->delete();

            // 確認していなかった主アドレスは、追加アドレスとしても確認前の扱いのまま
            if ($old !== null) $row->forceFill(['email' => $old, 'verified_at' => $account->emailVerifiedAt])->save();

            $this->accounts->updateEmail($accountId, $newEmail);
            $this->accounts->markEmailVerified($accountId);
        });

        // ログインに使うアドレスが変わったことを、今までのアドレスにも知らせる
        if ($old !== null) Mail::to($old)->send(new EmailChangeNoticeMail($newEmail));

        return $newEmail;
    }
}
