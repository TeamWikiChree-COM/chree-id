<?php
namespace App\Modules\Registry\Application;

use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * client_secret を作り直す。
 *
 * 実行した時点で古い secret は使えなくなるので、RP 側の設定を書き換えるまで
 * そのサービスからのログインは止まる。
 */
class RotateClientSecret {
    /**
     * @param OAuthClientModel $client 対象
     * @return string 新しい平文 secret。表示できるのはこの1回だけ
     * @throws RuntimeException public クライアントの場合
     */
    public function execute(OAuthClientModel $client): string {
        if (!$client->is_confidential) {
            throw new RuntimeException('public クライアントは secret を持ちません');
        }

        $secret = Str::random(64);
        $client->forceFill(['secret_hash' => hash('sha256', $secret)])->save();

        return $secret;
    }
}
