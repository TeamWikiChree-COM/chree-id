<?php
namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Domain\AuditAction;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * audit_events テーブルのモデル (監査ログの1行)
 *
 * @property string $id
 * @property string|null $auth_identity_id
 * @property string|null $actor_id
 * @property AuditAction $action
 * @property bool $succeeded
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class AuditEventModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'audit_events';

    /** 起きた時刻しか持たない。あとから書き換わる行ではないので updated_at は要らない */
    public const UPDATED_AT = null;

    #[\Override]
    protected $fillable = [
        'auth_identity_id',
        'actor_id',
        'action',
        'succeeded',
        'context',
        'ip_address',
        'user_agent',
    ];

    #[\Override]
    protected $casts = [
        'action' => AuditAction::class,
        'succeeded' => 'boolean',
        'context' => 'array',
    ];
}
