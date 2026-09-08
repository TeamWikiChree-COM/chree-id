<?php
namespace App\Modules\Registry\Application;

use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;

/**
 * 登録済みクライアントの設定を差し替える。
 *
 * client_id と secret は変えない。変えると接続中の RP が黙って止まるため、
 * secret の入れ替えは RotateClientSecret として別に用意している。
 */
class UpdateClient {
    /**
     * @param OAuthClientModel $client 対象
     * @param string $name サービス名
     * @param list<string> $redirectUris 許可するリダイレクト先
     * @param string $scopes 空白区切りのスコープ
     * @param ServiceTrust $trust 信頼状態
     * @return void
     */
    public function execute(
        OAuthClientModel $client,
        string $name,
        array $redirectUris,
        string $scopes,
        ServiceTrust $trust,
    ): void {
        $client->forceFill([
            'name' => $name,
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
            'trust' => $trust,
        ])->save();
    }
}
