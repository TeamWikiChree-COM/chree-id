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
     * @param string $name
     * @param array<string, string> $names 言語ごとの表示名。無い言語は $name を出す サービス名
     * @param list<string> $redirectUris 許可するリダイレクト先
     * @param string $scopes 空白区切りのスコープ
     * @param ServiceTrust $trust 信頼状態
     * @param bool $skipsConsent 同意画面を省略するか。信頼状態とは別の設定
     * @param bool $canProvision サービスアカウントを扱えるか。信頼状態とは別の設定
     * @param string|null $iconUrl アイコンの URL
     * @param string|null $settingsUrl 利用者に案内する設定画面の場所
     * @return void
     */
    public function execute(
        OAuthClientModel $client,
        string $name,
        array $names,
        array $redirectUris,
        string $scopes,
        ServiceTrust $trust,
        bool $skipsConsent = false,
        bool $canProvision = false,
        ?string $iconUrl = null,
        ?string $settingsUrl = null,
    ): void {
        $client->forceFill([
            'name' => $name,
            'names' => $names,
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
            'trust' => $trust,
            'skips_consent' => $skipsConsent,
            'can_provision' => $canProvision,
            'icon_url' => $iconUrl,
            'settings_url' => $settingsUrl,
        ])->save();
    }
}
