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

    // PHPではただの文字列だけどDBでは日付なのでDBに入れる時のメモ的なやつ
    // DB から取得: "2026-09-06 10:41:34"（文字列） → Carbon オブジェクト   ← これが本命
    // DB へ保存:   Carbon オブジェクト → DB 用の文字列                    ← こっちもやる
    #[\Override]
    protected $casts = [
        'email_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // なんとなくかいとく、いらんけどこれあったほうがおちつくやろ知らんけど
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }
}