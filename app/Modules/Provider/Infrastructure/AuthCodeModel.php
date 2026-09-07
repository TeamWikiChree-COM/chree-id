<?php
namespace App\Modules\Provider\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * oauth_auth_codes テーブルのモデル (認可コード)
 *
 * @property string $code_hash
 * @property string $client_id
 * @property string $chree_account_id
 * @property string $redirect_uri
 * @property string $scope
 * @property string|null $nonce
 * @property string|null $code_challenge
 * @property string|null $code_challenge_method
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 */
class AuthCodeModel extends Model {
    protected $table = 'oauth_auth_codes';

    protected $primaryKey = 'code_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code_hash',
        'client_id',
        'chree_account_id',
        'redirect_uri',
        'scope',
        'nonce',
        'code_challenge',
        'code_challenge_method',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * まだ使えるコードか (未使用かつ期限内)
     *
     * @return bool
     */
    public function isUsable(): bool {
        if ($this->used_at !== null) return false;

        return $this->expires_at->isFuture();
    }
}
