<?php
namespace App\Modules\Linking\Application;

use App\Modules\Credential\Application\AdoptPasswordHash;
use App\Modules\Credential\Application\SetPassword;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * サービス側でパスワードが変えられたとき、こちらにも反映する。
 *
 * **パスワードの正解を持っているのは ChreeID だけ**にしたいので、
 * サービスが自分の画面で変更を受け付けたら、必ずここへ流してもらう。
 * これが無いと、古いパスワードが向こうに残ったまま通り続ける。
 *
 * **束ねる人格を持つアカウントは触らせない。** そこは既に本人のもので、
 * 許すとサービス経由で他人のパスワードを差し替えられる。
 */
class ChangeServiceAccountPassword {
    /**
     * 断る理由は潰さない。呼び出し元が利用者への案内を分けられるようにする
     */
    public const OK = 'ok';
    public const NOT_FOUND = 'not_found';
    public const MANAGED = 'managed';
    public const UNSUPPORTED_HASH = 'unsupported_hash';

    public function __construct(
        private readonly SetPassword $passwords,
        private readonly AdoptPasswordHash $hashes,
        private readonly UserAccounts $userAccounts,
    ) {}

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param string $passwordHash 移行元が保存している bcrypt ハッシュ
     * @return string self::OK か、断った理由
     */
    public function execute(OAuthClientModel $client, string $serviceUserId, string $passwordHash): string {
        $serviceAccount = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        if ($serviceAccount === null) return self::NOT_FOUND;

        // 本人のものになっているアカウントは、サービス経由では触らせない
        if ($this->userAccounts->exists($serviceAccount->auth_identity_id)) return self::MANAGED;

        // bcrypt 以外を書き込むと、パスワードはあるのに絶対に通らないアカウントになる
        if (!$this->hashes->accepts($passwordHash)) return self::UNSUPPORTED_HASH;

        $this->passwords->executeHashed($serviceAccount->auth_identity_id, $passwordHash);

        return self::OK;
    }
}
