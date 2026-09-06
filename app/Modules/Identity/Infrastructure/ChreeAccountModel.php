<?php
namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * chree_acountsテーブルのモデル
 */
class ChreeAccountModel extends Model {
    // 主キーのidにULIDを自動で割り当てるトレイト
    use HasUlids;

    #[\Override]
    protected $table = "chree_accounts";

    // どうやらHasUlidsくんがgetKeyTypeとかメソッドごとに
    // 書き換えてるみたいなのでここは無効にした
    
    // // 主キーはULID(文字列)のため指定する、デフォルトではintなので
    // #[\Override]
    // protected $keyType = "string";
    
    // #[\Override]
    // public $incrementing = false; // ULIDなので自動連番は使わない

    // まとめて代入してよいカラム
    #[\Override]
    protected $fillable = [
        "email",
        "email_verified_at",
        "display_name",
    ];

    #[\Override]
    protected $casts = [
        'email_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }
}