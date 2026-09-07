<?php
namespace App\Modules\Identity\Domain;

/**
 * アカウントの発行経路
 *
 * 由来の記録であって権限ではない。どちらでも能力は同じ。
 * 要件定義書の「サービス側アカウント」(Wiki や users 行) とは別物。
 */
enum AccountOrigin: string {
    case USER = 'user';
    case SERVICE = 'service';
}
