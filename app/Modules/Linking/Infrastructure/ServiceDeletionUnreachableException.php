<?php
namespace App\Modules\Linking\Infrastructure;

use RuntimeException;

/**
 * サービスの削除依頼 URL から、使える返答を得られなかった。
 */
class ServiceDeletionUnreachableException extends RuntimeException {
}
