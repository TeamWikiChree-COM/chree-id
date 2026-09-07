<?php
namespace App\Modules\Credential\Infrastructure;

use App\Modules\Credential\Domain\CredentialType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * credentials テーブルのモデル (アカウントに紐づく認証手段)
 *
 * secret の中身は type によって性質が違う。詳しくはマイグレーションのコメントを参照。
 *
 * @property string $id
 * @property string $chree_account_id
 * @property CredentialType $type
 * @property string|null $identifier
 * @property string|null $secret
 * @property array<string, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
class CredentialModel extends Model {
    /**
     * 保存時に主キーへ ULID を自動採番する。
     * $keyType='string' と $incrementing=false もこのトレイトが持つので、個別指定は不要。
     */
    use HasUlids;

    #[\Override]
    protected $table = "credentials";

    #[\Override]
    protected $fillable = [
        "chree_account_id",
        "type",
        "identifier",
        "secret",
        "data",
        "last_used_at",
    ];

    // type を enum で受け渡しできるようにする (->value を書かなくて済む)
    #[\Override]
    protected $casts = [
        "type" => CredentialType::class,
        "data" => "array",
        "last_used_at" => "datetime",
    ];
}
