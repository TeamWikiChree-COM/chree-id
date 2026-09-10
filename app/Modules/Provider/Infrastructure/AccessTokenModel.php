<?php
namespace App\Modules\Provider\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * oauth_access_tokens テーブルのモデル
 *
 * @property string $id
 * @property string $token_hash
 * @property string $client_id
 * @property string $auth_identity_id
 * @property string $scope
 * @property string|null $auth_code_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class AccessTokenModel extends Model {
    use HasUlids;

    protected $table = 'oauth_access_tokens';

    protected $fillable = [
        'token_hash',
        'client_id',
        'auth_identity_id',
        'service_account_id',
        'scope',
        'auth_code_hash',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * @return bool
     */
    public function isUsable(): bool {
        if ($this->revoked_at !== null) return false;

        return $this->expires_at->isFuture();
    }

    /**
     * @return list<string>
     */
    public function scopes(): array {
        return array_values(array_filter(explode(' ', $this->scope), static fn (string $s): bool => $s !== ''));
    }
}
