<?php
namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * pending_email_changes テーブルのモデル (確認前のメールアドレス変更)
 *
 * @property string $id
 * @property string $chree_account_id
 * @property string $new_email
 * @property string $token_hash
 * @property \Illuminate\Support\Carbon $expires_at
 */
class PendingEmailChangeModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'pending_email_changes';

    #[\Override]
    protected $fillable = [
        'chree_account_id',
        'new_email',
        'token_hash',
        'expires_at',
    ];

    #[\Override]
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** トークンのハッシュを配列化やログに載せない */
    #[\Override]
    protected $hidden = ['token_hash'];

    /**
     * まだ使えるか (期限内か)
     *
     * @return bool
     */
    public function isUsable(): bool {
        return $this->expires_at->isFuture();
    }
}
