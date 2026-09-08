<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;

/**
 * 移行元が持っていたパスワードのハッシュを、そのまま引き受ける。
 *
 * サービス側も PHP の password_hash() を使っており、同じ bcrypt なので
 * password_verify() はこちらでもそのまま通る。平文を渡してもらう必要がない。
 *
 * 平文を運ばせないのは、経路にも受け手にも平文を残さないため。
 * ハッシュなら、渡す側が既に保存しているものをそのまま送るだけで済み、
 * 利用者がログインしてくるのを待たずに移せる。
 */
class AdoptPasswordHash {
    /**
     * 引き受けられる形式か。
     *
     * bcrypt 以外を書き込むと password_verify() が常に false を返し、
     * 「パスワードはあるのに絶対に通らない」アカウントが出来上がる。
     *
     * @param string $hash 移行元のハッシュ
     * @return bool
     */
    public function accepts(string $hash): bool {
        return password_get_info($hash)['algo'] === PASSWORD_BCRYPT;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $hash 移行元のハッシュ
     * @return bool 引き受けたら true。形式が違う、または既にパスワードがある場合は false
     */
    public function execute(string $accountId, string $hash): bool {
        if (!$this->accepts($hash)) return false;

        // 既に設定されているものは触らない。利用者が ChreeID 側で決め直した後に
        // サービスの古いハッシュで上書きすると、本人の変更が黙って巻き戻る
        $exists = CredentialModel::query()
            ->where('chree_account_id', $accountId)
            ->where('type', CredentialType::PASSWORD)
            ->exists();

        if ($exists) return false;

        CredentialModel::create([
            'chree_account_id' => $accountId,
            'type' => CredentialType::PASSWORD,
            'secret' => $hash,
        ]);

        return true;
    }
}
