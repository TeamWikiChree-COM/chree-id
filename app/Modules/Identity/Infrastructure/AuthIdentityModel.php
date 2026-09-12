<?php
namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\IconSource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * auth_identities テーブルのモデル (認証主体)
 *
 * @property string $id
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $display_name
 * @property AccountOrigin $origin
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property IconSource $icon_source
 * @property string|null $icon_path
 * @property string|null $locale 本人が選んだ表示言語。null は「選んでいない」
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AuthIdentityModel extends Model {
    // 主キーのidにULIDを自動で割り当てるトレイト
    use HasUlids;

    #[\Override]
    protected $table = "auth_identities";

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
        'origin',
        'icon_source',
        'icon_path',
        'locale',
    ];

    // PHPではただの文字列だけどDBでは日付なのでDBに入れる時のメモ的なやつ
    // DB から取得: "2026-09-06 10:41:34"（文字列） → Carbon オブジェクト   ← これが本命
    // DB へ保存:   Carbon オブジェクト → DB 用の文字列                    ← こっちもやる
    #[\Override]
    protected $casts = [
        'email_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'deleted_at' => 'datetime',
        'origin' => AccountOrigin::class,
        'icon_source' => IconSource::class,
    ];
    
    // なんとなくかいとく、いらんけどこれあったほうがおちつくやろ知らんけど
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }

    /**
     * 行を消したら、アップロードされたアイコンの実体も消す。
     *
     * 行だけ消すと、誰のものでもない画像がストレージに残り続ける。
     * **まとめて delete すると走らない。** 物理削除は1件ずつ消すこと。
     *
     * @return void
     */
    #[\Override]
    protected static function booted(): void {
        static::deleting(function (self $model): void {
            if ($model->icon_path === null) return;

            Storage::disk('local')->delete($model->icon_path);
        });
    }
}