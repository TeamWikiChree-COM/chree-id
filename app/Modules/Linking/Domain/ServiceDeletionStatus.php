<?php
namespace App\Modules\Linking\Domain;

/**
 * サービスへの削除依頼の状態
 */
enum ServiceDeletionStatus: string {
    /** まだ届いていない。再送の対象 */
    case PENDING = 'pending';

    /** サービスが受け付けた */
    case ACCEPTED = 'accepted';

    /** サービスが断った。本人へ知らせる */
    case REFUSED = 'refused';

    /** 再送を打ち切った */
    case FAILED = 'failed';
}
