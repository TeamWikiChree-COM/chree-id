<?php
namespace App\Modules\Device\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * login_sessions テーブルのモデル (ログイン中の端末)
 *
 * 主キーはセッションIDそのもの。採番しないので HasUlids は使わない。
 *
 * @property string $id
 * @property string $auth_identity_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $last_active_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class LoginSessionModel extends Model {
    #[\Override]
    protected $table = 'login_sessions';

    #[\Override]
    protected $keyType = 'string';

    #[\Override]
    public $incrementing = false;

    #[\Override]
    protected $fillable = [
        'id',
        'auth_identity_id',
        'ip_address',
        'user_agent',
        'last_active_at',
    ];

    #[\Override]
    protected $casts = [
        'last_active_at' => 'datetime',
    ];
}
