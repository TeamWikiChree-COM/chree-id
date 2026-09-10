<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\SetPassword;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Identity\Application\RequestEmailChange;
use App\Modules\Identity\Application\RequestEmailVerification;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use Illuminate\Support\Facades\DB;

/**
 * サービスが裏で作ったアカウントを、本人のものにする。
 *
 * 移行元のパスワードは発行時に引き継いでいるので、ここでの仕事は
 * 「本人がこのアカウントを自分のものだと認めた」記録を残すこと。
 * そのうえで、以後 ChreeID 単体でも入れるように認証手段と
 * 連絡先を本人の手で確定させる。認証手段はパスワードに限らず、
 * パスキーや外部ログイン (Google 等) でもよい。
 */
class ClaimServiceAccount {

    private readonly AuthIdentityRepository $accounts;
    private readonly SetPassword $passwords;
    private readonly CredentialRepository $credentials;
    private readonly RequestEmailChange $requestEmailChange;
    private readonly RequestEmailVerification $requestVerification;
    private readonly ClaimTickets $tickets;
    private readonly UserAccounts $userAccounts;

    public function __construct(AuthIdentityRepository $accounts, SetPassword $passwords, CredentialRepository $credentials, RequestEmailChange $requestEmailChange, RequestEmailVerification $requestVerification, ClaimTickets $tickets, UserAccounts $userAccounts) {
        $this->accounts = $accounts;
        $this->passwords = $passwords;
        $this->credentials = $credentials;
        $this->requestEmailChange = $requestEmailChange;
        $this->requestVerification = $requestVerification;
        $this->tickets = $tickets;
        $this->userAccounts = $userAccounts;
    }

    /**
     * パスワードを決めて引き取る。
     *
     * @param ServiceAccountModel $link 引き取る紐付け
     * @param string $password 本人が決めたパスワード
     * @param string|null $displayName 表示名
     * @param string|null $email 本人が入力したアドレス。変更しないなら null
     * @return string アカウントID (ULID)
     * @throws ClaimException
     */
    public function executeWithPassword(
        ServiceAccountModel $link,
        string $password,
        ?string $displayName = null,
        ?string $email = null,
    ): string {
        return $this->finalize($link, $displayName, $email, function (string $accountId) use ($password): void {
            $this->passwords->execute($accountId, $password);
        });
    }

    /**
     * 既にある認証手段のまま引き取る。
     *
     * **移行元のパスワードは発行時に引き継いでいるので、たいていはこちらで足りる。**
     * わざわざ新しい認証手段を決め直させる必要はない (ARCHITECTURE.md 8.4 の改訂)。
     * パスキーや Google をこの場で登録した場合も、登録後にここへ来る。
     *
     * @param ServiceAccountModel $link 引き取る紐付け
     * @param string|null $displayName 表示名
     * @param string|null $email 本人が入力したアドレス。変更しないなら null
     * @param list<string>|null $keepCredentialIds 引き継ぐものとして選ばれたID。null なら全部残す
     * @return string アカウントID (ULID)
     * @throws ClaimException 認証手段が確認できない場合を含む
     */
    public function executeWithExistingCredential(
        ServiceAccountModel $link,
        ?string $displayName = null,
        ?string $email = null,
        ?array $keepCredentialIds = null,
    ): string {
        return $this->finalize($link, $displayName, $email, function (string $accountId) use ($keepCredentialIds): void {
            // 引き継ぐものを選ばせている場合は、外されたものを落とす
            if ($keepCredentialIds !== null) $this->dropUnchosen($accountId, $keepCredentialIds);

            // 1つも無いと、移行したあと誰も入れなくなる
            if (!$this->credentials->hasAny($accountId)) throw new ClaimException(ClaimException::NO_CREDENTIAL);
        });
    }

    /**
     * 選ばれなかった認証手段を落とす。
     *
     * 移行では認証主体が変わらないので、引き継ぐ／引き継がないは
     * 「その ChreeID に残すかどうか」になる。
     *
     * @param string $accountId アカウントID (ULID)
     * @param list<string> $keepIds 残すものとして選ばれたID
     * @return void
     */
    private function dropUnchosen(string $accountId, array $keepIds): void {
        $rows = CredentialModel::query()->where('auth_identity_id', $accountId)->get();

        foreach ($rows as $row) {
            // 復旧コードは TOTP に付随するので、単独では消さない
            if ($row->type === CredentialType::RECOVERY_CODE) continue;
            if (in_array($row->id, $keepIds, true)) continue;

            $row->delete();
        }
    }

    /**
     * @param ServiceAccountModel $link 引き取る紐付け
     * @param string|null $displayName 表示名
     * @param string|null $email 本人が入力したアドレス。変更しないなら null
     * @param callable(string):void $setupCredential 認証手段を確定させる処理。アカウントIDを受け取る
     * @return string アカウントID (ULID)
     * @throws ClaimException
     */
    private function finalize(
        ServiceAccountModel $link,
        ?string $displayName,
        ?string $email,
        callable $setupCredential,
    ): string {
        $account = $this->accounts->findById($link->auth_identity_id);

        // 既に本人のものなら触らない。許すと他人のパスワードを差し替えられる。
        // 判定は UserAccount の有無で行う。origin は出自の記録なので使わない
        if ($account === null || $this->userAccounts->exists($account->id)) {
            throw new ClaimException(ClaimException::ALREADY_CLAIMED);
        }

        // 打ち直された場合だけ変更として扱う。同じものを送り返されたときは確認だけでよい
        $changing = $email !== null && $email !== $account->email;

        if ($changing && !$this->requestEmailChange->execute($account->id, (string)$email)) {
            throw new ClaimException(ClaimException::EMAIL_TAKEN);
        }

        DB::transaction(function () use ($link, $account, $displayName, $setupCredential): void {
            $setupCredential($account->id);

            if ($displayName !== null) $this->accounts->updateDisplayName($account->id, $displayName);

            // ここで束ねる人格ができる。origin (出自) は service のまま触らない
            $this->userAccounts->ensure($account->id);

            $link->forceFill(['claimed_at' => now()])->save();
        });

        $this->tickets->consume($link);

        // 未確認のまま残るアドレスは、この機会に到達性を確かめておく。
        // 変更を申し込んだ場合はそちらの確認メールが出ているので何もしない
        if (!$changing) $this->requestVerification->execute($account->id);

        return $account->id;
    }
}
