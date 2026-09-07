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
