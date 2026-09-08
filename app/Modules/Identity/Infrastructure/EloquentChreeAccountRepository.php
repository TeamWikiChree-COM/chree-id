<?php
namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\ChreeAccount;
use App\Modules\Identity\Domain\ChreeAccountRepository;

/**
 * ChreeAccountRepository の Eloquent 実装
 */
class EloquentChreeAccountRepository implements ChreeAccountRepository {
    /**
     * @param string $id アカウントID (ULID)
     * @return ChreeAccount|null
     */
    public function findById(string $id): ?ChreeAccount {
        $model = ChreeAccountModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * @param string $email メールアドレス
     * @return ChreeAccount|null
     */
    public function findByEmail(string $email): ?ChreeAccount {
        $model = ChreeAccountModel::query()->where('email', $email)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * @param AccountOrigin $origin 発行経路
     * @param string|null $email 連絡先
     * @param string|null $displayName 表示名
     * @return ChreeAccount
     */
    public function create(AccountOrigin $origin, ?string $email = null, ?string $displayName = null): ChreeAccount {
        $model = ChreeAccountModel::create([
            'origin' => $origin,
            'email' => $email,
            'display_name' => $displayName,
        ]);

        return $this->toDomain($model);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function markEmailVerified(string $id): void {
        ChreeAccountModel::query()->whereKey($id)->update(['email_verified_at' => now()]);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @param string|null $displayName 表示名
     * @return void
     */
    public function updateDisplayName(string $id, ?string $displayName): void {
        ChreeAccountModel::query()->whereKey($id)->update(['display_name' => $displayName]);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @param AccountOrigin $origin 新しい発行経路
     * @return void
     */
    public function changeOrigin(string $id, AccountOrigin $origin): void {
        ChreeAccountModel::query()->whereKey($id)->update(['origin' => $origin]);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @param string $email 新しいメールアドレス
     * @return void
     */
    public function updateEmail(string $id, string $email): void {
        ChreeAccountModel::query()->whereKey($id)->update(['email' => $email]);
    }

    /**
     * @param ChreeAccountModel $model
     * @return ChreeAccount
     */
    private function toDomain(ChreeAccountModel $model): ChreeAccount {
        return new ChreeAccount(
            $model->id,
            $model->email,
            $model->email_verified_at,
            $model->display_name,
            $model->origin,
            $model->suspended_at,
        );
    }
}
