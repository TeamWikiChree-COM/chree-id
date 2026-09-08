<?php
namespace App\Modules\Linking\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * service_account_links テーブルのモデル (サービス側ユーザーと ChreeID の対応)
 *
 * @property string $id
 * @property string $client_id
 * @property string $chree_account_id
 * @property string $service_user_id
 * @property string|null $service_email
 * @property \Illuminate\Support\Carbon|null $claimed_at
 */
class ServiceAccountLinkModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'service_account_links';

    #[\Override]
    protected $fillable = [
        'client_id',
        'chree_account_id',
        'service_user_id',
        'service_email',
    ];

    #[\Override]
    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    /**
     * 利用者が引き取り済みか。
     *
     * @return bool
     */
    public function isClaimed(): bool {
        return $this->claimed_at !== null;
    }
}
