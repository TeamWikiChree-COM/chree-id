<?php
namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;

/**
 * AuthIdentityRepository の Eloquent 実装
 */
class EloquentAuthIdentityRepository implements AuthIdentityRepository {
    /**
     * @param string $id アカウントID (ULID)
     * @return AuthIdentity|null
     */
    public function findById(string $id): ?AuthIdentity {
        $model = AuthIdentityModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * @param string $email メールアドレス
     * @return AuthIdentity|null
     */
    public function findAllByEmail(string $email): array {
        $found = [];

        foreach (AuthIdentityModel::query()->where('email', $email)->orderBy('id')->get() as $model) {
            $found[] = $this->toDomain($model);
        }

        return $found;
    }

    /**
     * @param AccountOrigin $origin 発行経路
     * @param string|null $email 連絡先
     * @param string|null $displayName 表示名
     * @return AuthIdentity
     */
    public function create(AccountOrigin $origin, ?string $email = null, ?string $displayName = null): AuthIdentity {
        $model = AuthIdentityModel::create([
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
        AuthIdentityModel::query()->whereKey($id)->update(['email_verified_at' => now()]);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @param string|null $displayName 表示名
     * @return void
     */
    public function updateDisplayName(string $id, ?string $displayName): void {
        AuthIdentityModel::query()->whereKey($id)->update(['display_name' => $displayName]);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @param AccountOrigin $origin 新しい発行経路
     * @return void
     */
    public function changeOrigin(string $id, AccountOrigin $origin): void {
        AuthIdentityModel::query()->whereKey($id)->update(['origin' => $origin]);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @param string $email 新しいメールアドレス
     * @return void
     */
    public function updateEmail(string $id, string $email): void {
        AuthIdentityModel::query()->whereKey($id)->update(['email' => $email]);
    }

    /**
     * @param string $id アカウントID (ULID)
     * @return void
     */
    public function suspend(string $id): void {
        AuthIdentityModel::query()->whereKey($id)->update(['suspended_at' => now()]);
    }

    /**
     * @param AuthIdentityModel $model
     * @return AuthIdentity
     */
    private function toDomain(AuthIdentityModel $model): AuthIdentity {
        return new AuthIdentity(
            $model->id,
            $model->email,
            $model->email_verified_at,
            $model->display_name,
            $model->origin,
            $model->suspended_at,
        );
    }
}
