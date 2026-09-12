<?php
namespace App\Modules\Audit\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * login_events テーブルのモデル (ログインの出来事)
 *
 * @property string $id
 * @property string $auth_identity_id
 * @property string $method
 * @property bool $succeeded
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class LoginEventModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'login_events';

    /** 起きた時刻しか持たない。あとから書き換わるものではないので updated_at は要らない */
    public const UPDATED_AT = null;

    #[\Override]
    protected $fillable = [
        'auth_identity_id',
        'method',
        'succeeded',
        'ip_address',
        'user_agent',
    ];

    #[\Override]
    protected $casts = [
        'succeeded' => 'boolean',
    ];
}
