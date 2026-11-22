<?php
namespace App\Console\Commands;

use App\Modules\Linking\Application\DeliverServiceDeletions;
use Illuminate\Console\Command;

/**
 * 退会時に届かなかったサービスへの削除依頼を再送する。
 */
class SendServiceDeletions extends Command {
    #[\Override]
    protected $signature = 'chreeid:send-service-deletions';

    #[\Override]
    protected $description = '未送信のサービス削除依頼を再送する';

    /**
     * @param DeliverServiceDeletions $deliver
     * @return int
     */
    public function handle(DeliverServiceDeletions $deliver): int {
        $delivered = $deliver->pending();
        $this->info("削除依頼 {$delivered} 件を送信しました");

        return self::SUCCESS;
    }
}
