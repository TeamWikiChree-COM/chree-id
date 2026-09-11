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
     * @return list<AuthIdentity> 古い順
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

    public function delete(string $id): void {
        AuthIdentityModel::query()->whereKey($id)->delete();
    }

    public function softDelete(string $id): void {
        AuthIdentityModel::query()->whereKey($id)->update([
            'deleted_at' => now(),
            'suspended_at' => now(),
        ]);
    }

    public function restore(string $id): void {
        AuthIdentityModel::query()->whereKey($id)->update([
            'deleted_at' => null,
            'suspended_at' => null,
        ]);
    }

    public function countDeletedBefore(int $days): int {
        return $this->dueForPurge($days)->count();
    }

    public function purgeDeletedBefore(int $days): int {
        // 1件ずつ消す。まとめて delete すると FK の cascade が効かない環境がある
        $due = $this->dueForPurge($days)->get();

        foreach ($due as $model) $model->delete();

        return $due->count();
    }

    /**
     * 数えるのと消すのが同じ条件でないと、画面に出した件数と結果が食い違う。
     *
     * @param int $days 退会から何日残すか
     * @return \Illuminate\Database\Eloquent\Builder<AuthIdentityModel>
     */
    private function dueForPurge(int $days): \Illuminate\Database\Eloquent\Builder {
        return AuthIdentityModel::query()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', now()->subDays($days));
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
            $model->deleted_at,
        );
    }
}
