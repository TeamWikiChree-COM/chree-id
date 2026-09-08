<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\RequestEmailChange;
use App\Modules\Identity\Application\RequestEmailVerification;
use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use Illuminate\Support\Facades\DB;

/**
 * サービスが裏で作ったアカウントを、本人のものにする。
 *
 * 移行元のパスワードは発行時に引き継いでいるので、ここでの仕事は
 * 「本人がこのアカウントを自分のものだと認めた」記録を残すこと。
 * そのうえで、以後 ChreeID 単体でも入れるように認証手段と
 * 連絡先を本人の手で確定させる。
 */
class ClaimServiceAccount {
    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly SetPassword $passwords,
        private readonly RequestEmailChange $requestEmailChange,
        private readonly RequestEmailVerification $requestVerification,
        private readonly ClaimTickets $tickets,
    ) {}

    /**
     * @param ServiceAccountLinkModel $link 引き取る紐付け
     * @param string $password 本人が決めたパスワード
     * @param string|null $displayName 表示名
     * @param string|null $email 本人が入力したアドレス。変更しないなら null
     * @return string アカウントID (ULID)
     * @throws ClaimException
     */
    public function execute(
        ServiceAccountLinkModel $link,
        string $password,
        ?string $displayName = null,
        ?string $email = null,
    ): string {
        $account = $this->accounts->findById($link->chree_account_id);

        // 既に本人のものなら触らない。ここで上書きすると、
        // 発行時に既存アカウントへ寄せた人のパスワードを巻き戻してしまう
        if ($account === null || $account->origin === AccountOrigin::USER) {
            throw new ClaimException(ClaimException::ALREADY_CLAIMED);
        }

        // 打ち直された場合だけ変更として扱う。同じものを送り返されたときは確認だけでよい
        $changing = $email !== null && $email !== $account->email;

        if ($changing && !$this->requestEmailChange->execute($account->id, (string)$email)) {
            throw new ClaimException(ClaimException::EMAIL_TAKEN);
        }

        DB::transaction(function () use ($link, $account, $password, $displayName): void {
            $this->passwords->execute($account->id, $password);

            if ($displayName !== null) $this->accounts->updateDisplayName($account->id, $displayName);

            $this->accounts->changeOrigin($account->id, AccountOrigin::USER);

            $link->forceFill(['claimed_at' => now()])->save();
        });

        $this->tickets->consume($link);

        // 未確認のまま残るアドレスは、この機会に到達性を確かめておく。
        // 変更を申し込んだ場合はそちらの確認メールが出ているので何もしない
        if (!$changing) $this->requestVerification->execute($account->id);

        return $account->id;
    }
}
