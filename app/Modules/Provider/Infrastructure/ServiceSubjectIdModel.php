<?php
namespace App\Modules\Provider\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * service_subject_ids テーブルのモデル (サービスごとに見せる sub)
 *
 * @property string $id
 * @property string $client_id
 * @property string $chree_account_id
 * @property string $sub
 */
class ServiceSubjectIdModel extends Model {
    use HasUlids;

    protected $table = 'service_subject_ids';

    protected $fillable = [
        'client_id',
        'chree_account_id',
        'sub',
    ];
}
