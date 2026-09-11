<?php
namespace App\Modules\Registry\Application;

use App\Modules\Credential\Infrastructure\OneTimeTokenModel;
use App\Modules\Identity\Infrastructure\PendingEmailChangeModel;
use App\Modules\Identity\Infrastructure\PendingRegistrationModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * 使い終わった使い捨てトークンと、確認されなかった登録申し込みを消す。
 *
 * `pending_registrations` にはパスワードハッシュが入っているので、
 * 期限が切れたものを残しておく理由が無い。
 *
 * **削除そのものはここが持つ。** Artisan コマンドと管理画面の両方から呼ぶため。
 * HTTP から Artisan を起動する形にすると、出力の整形まで巻き込むことになる。
 *
 * 数えるのと消すのは同じ条件を使う。**条件を二重に書くと、画面に出した件数と
 * 実際に消える件数が食い違う。**
 */
class PruneTokens {
    /** 使用済みトークンを残す既定の日数 */
    public const DEFAULT_DAYS = 7;

    /**
     * 消さずに、消える件数だけを数える。
     *
     * @param int $days 使用済みトークンを残す日数。0 以下なら既定を使う
     * @return PrunedTokens 消える件数の内訳
     */
    public function pending(int $days = self::DEFAULT_DAYS): PrunedTokens {
        return new PrunedTokens(
            registrations: $this->staleRegistrations()->count(),
            emailChanges: $this->staleEmailChanges()->count(),
            expiredTokens: $this->expiredTokens()->count(),
            usedTokens: $this->usedTokens($days)->count(),
        );
    }

    /**
     * @param int $days 使用済みトークンを残す日数。0 以下なら既定を使う
     * @return PrunedTokens 消した件数の内訳
     */
    public function execute(int $days = self::DEFAULT_DAYS): PrunedTokens {
        return new PrunedTokens(
            registrations: $this->prune($this->staleRegistrations()),
            emailChanges: $this->prune($this->staleEmailChanges()),
            expiredTokens: $this->prune($this->expiredTokens()),
            usedTokens: $this->prune($this->usedTokens($days)),
        );
    }

    /**
     * @return Builder<PendingRegistrationModel> 確認されないまま期限が切れた登録申し込み
     */
    private function staleRegistrations(): Builder {
        return PendingRegistrationModel::query()->where('expires_at', '<', now());
    }

    /**
     * @return Builder<PendingEmailChangeModel> 確認されないまま期限が切れたアドレス変更
     */
    private function staleEmailChanges(): Builder {
        return PendingEmailChangeModel::query()->where('expires_at', '<', now());
    }

    /**
     * @return Builder<OneTimeTokenModel> 使われないまま期限が切れたトークン
     */
    private function expiredTokens(): Builder {
        return OneTimeTokenModel::query()->whereNull('used_at')->where('expires_at', '<', now());
    }

    /**
     * 使用済みは二重投入の検知に使うので、すぐには消さず少し残す。
     *
     * @param int $days 残す日数。0 以下なら既定を使う
     * @return Builder<OneTimeTokenModel> 残す期間を過ぎた使用済みトークン
     */
    private function usedTokens(int $days): Builder {
        $keepFor = $days > 0 ? $days : self::DEFAULT_DAYS;

        return OneTimeTokenModel::query()
            ->whereNotNull('used_at')
            ->where('used_at', '<', now()->subDays($keepFor));
    }

    /**
     * @param Builder<covariant \Illuminate\Database\Eloquent\Model> $query 消す対象
     * @return int 消した件数
     */
    private function prune(Builder $query): int {
        $deleted = $query->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}
