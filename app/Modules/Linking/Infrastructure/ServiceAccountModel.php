<?php
namespace App\Modules\Linking\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * service_accounts テーブルのモデル (サービス側ユーザーと ChreeID の対応)
 *
 * @property string $id
 * @property string $client_id
 * @property string $auth_identity_id
 * @property string|null $service_user_id
 * @property string|null $sub
 * @property string|null $service_email
 * @property string|null $claim_token_hash
 * @property \Illuminate\Support\Carbon|null $claim_expires_at
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ServiceAccountModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'service_accounts';

    #[\Override]
    protected $fillable = [
        'client_id',
        'auth_identity_id',
        'service_user_id',
        'sub',
        'service_email',
        'claim_token_hash',
        'claim_expires_at',
    ];

    #[\Override]
    protected $casts = [
        'claim_expires_at' => 'datetime',
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
