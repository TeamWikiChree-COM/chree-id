<?php
namespace App\Modules\Credential\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * one_time_tokens テーブルのモデル (メールで送る使い捨てトークン)
 *
 * 平文は発行時にしか存在しない。DB にはハッシュだけ置く。
 *
 * @property string $id
 * @property string $auth_identity_id
 * @property string $token_hash
 * @property string $purpose
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 */
class OneTimeTokenModel extends Model {
    use HasUlids;

    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_VERIFY_EMAIL = 'verify_email';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';
    /** サービス経由のログインで、パスワードが通った後に2要素目を待つ合図 */
    public const PURPOSE_SERVICE_SECOND_FACTOR = 'service_second_factor';

    #[\Override]
    protected $table = 'one_time_tokens';

    #[\Override]
    protected $fillable = [
        'auth_identity_id',
        'token_hash',
        'purpose',
        'expires_at',
    ];

    #[\Override]
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * まだ使えるトークンか (未使用かつ期限内)
     *
     * @return bool
     */
    public function isUsable(): bool {
        if ($this->used_at !== null) return false;

        return $this->expires_at->isFuture();
    }
}
