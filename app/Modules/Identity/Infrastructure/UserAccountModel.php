<?php
namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * user_accounts テーブルのモデル (複数の ServiceAccount を束ねる人格)
 *
 * **行の有無が「束ねているか」を表す。** origin は出自の記録であって、
 * この判定には使わない (KAKUTEI.md)。
 *
 * @property string $id
 * @property string $auth_identity_id
 */
class UserAccountModel extends Model {
    use HasUlids;

    #[\Override]
    protected $table = 'user_accounts';

    #[\Override]
    protected $fillable = ['auth_identity_id'];
}
