<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Infrastructure\CredentialModel;
use RuntimeException;

/**
 * 認証手段に付けた名前を変える。
 *
 * 名前を持つのはパスキーだけ。パスワードや外部アカウントは、何であるかが
 * 種別と連携先で決まるので、本人が付け替える余地がない。
 */
class RenameCredential {
    /** 名前の上限。長すぎると一覧の行が読めなくなる */
    private const MAX_LENGTH = 60;

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string $credentialId 認証手段のID (ULID)
     * @param string $label 新しい名前
     * @return void
     * @throws RuntimeException 見つからない、名前を持てない種別、名前が空の場合
     */
    public function execute(string $accountId, string $credentialId, string $label): void {
        $name = mb_substr(trim($label), 0, self::MAX_LENGTH);
        if ($name === '') throw new RuntimeException(__('settings.credential.name_required'));

        $row = CredentialModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('id', $credentialId)
            ->first();

        if ($row === null) throw new RuntimeException(__('settings.credential.not_found'));
        if ($row->type !== CredentialType::PASSKEY) throw new RuntimeException(__('settings.credential.cannot_rename'));

        // data には sign_count など検証に使う値も入っている。label だけ差し替える
        $data = is_array($row->data) ? $row->data : [];
        $data['label'] = $name;

        $row->forceFill(['data' => $data])->save();
    }
}
