<?php
namespace App\Modules\Linking\Application;

use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use Illuminate\Support\Carbon;

/**
 * 発行したての入場券。
 *
 * 平文トークンが手に入るのは発行の瞬間だけなので、
 * 保存先のモデルとは別にここで持ち回す。
 */
class ClaimTicket {
    /**
     * @param ServiceAccountLinkModel $link 対象の紐付け
     * @param string $token サービスに一度だけ返す平文トークン
     * @param Carbon $expiresAt 失効時刻
     */
    public function __construct(
        public readonly ServiceAccountLinkModel $link,
        public readonly string $token,
        public readonly Carbon $expiresAt,
    ) {}
}
