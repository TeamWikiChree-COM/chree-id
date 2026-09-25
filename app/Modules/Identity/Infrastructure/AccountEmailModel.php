<?php
namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * account_emails テーブルのモデル (主アドレスとは別に持つ追加のメールアドレス)
 *
 * @property string $id
 * @property string $auth_identity_id
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $token_hash
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AccountEmailModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'account_emails';

    #[\Override]
    protected $fillable = [
        'auth_identity_id',
        'email',
        'verified_at',
        'token_hash',
        'token_expires_at',
    ];

    #[\Override]
    protected $casts = [
        'verified_at' => 'datetime',
        'token_expires_at' => 'datetime',
    ];

    /** トークンのハッシュを配列化やログに載せない */
    #[\Override]
    protected $hidden = ['token_hash'];

    /**
     * @return bool 確認が済んでいるか
     */
    public function isVerified(): bool {
        return $this->verified_at !== null;
    }
}
