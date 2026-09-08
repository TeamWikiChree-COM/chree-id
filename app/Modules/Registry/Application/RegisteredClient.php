<?php
namespace App\Modules\Registry\Application;

use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 登録直後のクライアント。
 *
 * 平文の client_secret を持てるのはこの瞬間だけで、DB にはハッシュしか残らない。
 * 再表示はできないので、受け取った側が控える責任を負う。
 */
readonly class RegisteredClient {
    /**
     * @param OAuthClientModel $client 保存されたクライアント
     * @param string|null $secret 平文の client_secret。public クライアントでは null
     */
    public function __construct(
        public OAuthClientModel $client,
        public ?string $secret,
    ) {}
}
