<?php
namespace App\Modules\Admin\Application;

use App\Modules\Credential\Application\RemoveCredential;
use App\Modules\Linking\Application\SplitException;
use App\Modules\Linking\Application\SplitServiceAccount;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Application\RevokeAccessTokens;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 管理者による、認証手段とサービスアカウントの紐付けの操作。
 *
 * 本人が画面から行う操作と同じものを、問い合わせ対応やデータの修正のために運営が代行する。
 */
class ManageAccountLinks {
    private readonly AdminTarget $target;
    private readonly RemoveCredential $credentials;
    private readonly SplitServiceAccount $split;
    private readonly RevokeAccessTokens $tokens;

    public function __construct(AdminTarget $target, RemoveCredential $credentials, SplitServiceAccount $split, RevokeAccessTokens $tokens) {
        $this->target = $target;
        $this->credentials = $credentials;
        $this->split = $split;
        $this->tokens = $tokens;
    }

    /**
     * 認証手段を1件消す。最後の1件は消せない (本人が入れなくなる)。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $accountId 対象のアカウントID
     * @param string $credentialId 消す認証手段のID
     * @return void
     * @throws RuntimeException
     */
    public function removeCredential(string $actorId, string $accountId, string $credentialId): void {
        $this->target->require($actorId, $accountId);

        $this->credentials->executeById($accountId, $credentialId);
    }

    /**
     * サービスアカウントを新しい認証主体へ切り離す。sub は変わらないので、サービスからは同じ人に見える。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $accountId 対象のアカウントID
     * @param string $linkId 切り離すサービスアカウントのID
     * @param list<string> $credentialIds 新しい側へ複製する認証手段のID
     * @return string 新しい認証主体のID
     * @throws RuntimeException
     */
    public function split(string $actorId, string $accountId, string $linkId, array $credentialIds): string {
        $this->target->require($actorId, $accountId);
        $link = $this->link($accountId, $linkId);

        try {
            return $this->split->execute($link, null, null, $credentialIds);
        } catch (SplitException $e) {
            throw new RuntimeException(__('admin.account.split_failed.' . $e->reason), 0, $e);
        }
    }

    /**
     * 誤って作られた紐付けを消す。**sub も消えるので、サービスからは別人に見えるようになる。**
     *
     * 発行済みのトークンが残っていると、消した紐付けのままサービスに入れてしまうので失効させる。
     *
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $accountId 対象のアカウントID
     * @param string $linkId 消すサービスアカウントのID
     * @return void
     * @throws RuntimeException
     */
    public function unlink(string $actorId, string $accountId, string $linkId): void {
        $this->target->require($actorId, $accountId);
        $link = $this->link($accountId, $linkId);

        DB::transaction(function () use ($link): void {
            $this->tokens->forServiceAccount($link->id);

            $link->delete();
        });
    }

    /**
     * 画面から来たIDは信用しない。そのアカウントの持ち物かをここで確かめる。
     *
     * @param string $accountId 対象のアカウントID
     * @param string $linkId サービスアカウントのID
     * @return ServiceAccountModel
     * @throws RuntimeException
     */
    private function link(string $accountId, string $linkId): ServiceAccountModel {
        $link = ServiceAccountModel::query()
            ->where('id', $linkId)
            ->where('auth_identity_id', $accountId)
            ->first();

        if ($link === null) throw new RuntimeException(__('admin.account.link_not_found'));

        return $link;
    }
}
