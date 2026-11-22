<?php
namespace App\Modules\Linking\Infrastructure;

use App\Modules\Linking\Domain\ServiceDeletionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * service_deletion_requests テーブルのモデル (退会時にサービスへ送る削除依頼)
 *
 * @property string $id
 * @property string $client_id
 * @property string|null $sub
 * @property string|null $service_user_id
 * @property string|null $notify_email
 * @property ServiceDeletionStatus $status
 * @property int $attempts
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ServiceDeletionRequestModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'service_deletion_requests';

    #[\Override]
    protected $fillable = [
        'client_id',
        'sub',
        'service_user_id',
        'notify_email',
        'status',
        'attempts',
        'reason',
        'sent_at',
    ];

    #[\Override]
    protected $casts = [
        'status' => ServiceDeletionStatus::class,
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];
}
