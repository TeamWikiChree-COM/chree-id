<?php
namespace App\Modules\Federation\Domain;

use RuntimeException;

/**
 * 同じメールのアカウントが既にあるが、自動で紐付けてよいと判断できない場合。
 *
 * 黙って2つ目のアカウントを作ると、本人が「なぜか別アカウントになる」状態に陥る。
 * 既存アカウントでログインしてから連携してもらう。
 */
class FederationLinkConflict extends RuntimeException {
}
