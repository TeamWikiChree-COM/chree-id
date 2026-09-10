<?php
namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * auth_identity_aliases テーブルのモデル (統合で消えた側のIDの行き先)
 *
 * 永久保持する。統合しても sub は付け替えないので、古いIDを指したまま
 * 来る問い合わせが残り続ける。その転送表がこれ。
 *
 * @property string $legacy_id
 * @property string $current_id
 * @property \Illuminate\Support\Carbon $merged_at
 */
class AuthIdentityAliasModel extends Model {
    #[\Override]
    protected $table = 'auth_identity_aliases';

    #[\Override]
    protected $primaryKey = 'legacy_id';

    #[\Override]
    protected $keyType = 'string';

    #[\Override]
    public $incrementing = false;

    #[\Override]
    public $timestamps = false;

    #[\Override]
    protected $fillable = ['legacy_id', 'current_id', 'merged_at'];

    #[\Override]
    protected $casts = ['merged_at' => 'datetime'];
}
