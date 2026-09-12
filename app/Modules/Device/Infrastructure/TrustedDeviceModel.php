<?php
namespace App\Modules\Device\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * trusted_devices テーブルのモデル (2段階目を省略してよい端末)
 *
 * token_hash にはハッシュしか入らない。平文はクッキーとして端末だけが持つ。
 *
 * @property string $id
 * @property string $auth_identity_id
 * @property string $token_hash
 * @property string|null $label
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class TrustedDeviceModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'trusted_devices';

    #[\Override]
    protected $fillable = [
        'auth_identity_id',
        'token_hash',
        'label',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
    ];

    #[\Override]
    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
