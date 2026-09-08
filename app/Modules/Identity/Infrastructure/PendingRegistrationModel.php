<?php
namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * pending_registrations テーブルのモデル (メール確認前の登録申し込み)
 *
 * @property string $id
 * @property string $email
 * @property string $token_hash
 * @property \Illuminate\Support\Carbon $expires_at
 */
class PendingRegistrationModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'pending_registrations';

    #[\Override]
    protected $fillable = [
        'email',
        'token_hash',
        'expires_at',
    ];

    #[\Override]
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** トークンのハッシュを配列化やログに載せない */
    #[\Override]
    protected $hidden = [
        'token_hash',
    ];

    /**
     * まだ使えるか (期限内か)
     *
     * @return bool
     */
    public function isUsable(): bool {
        return $this->expires_at->isFuture();
    }
}
