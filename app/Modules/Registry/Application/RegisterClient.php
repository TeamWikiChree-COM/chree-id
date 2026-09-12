<?php
namespace App\Modules\Registry\Application;

use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Str;

/**
 * サービス (OAuth クライアント) を登録する。
 *
 * コンソールコマンドと管理画面の両方から呼ばれる。
 * client_id の採番と secret の扱いを1か所に閉じ込めるための置き場。
 */
class RegisterClient {
    /**
     * @param string $name
     * @param array<string, string> $names 言語ごとの表示名。無い言語は $name を出す サービス名
     * @param list<string> $redirectUris 許可するリダイレクト先
     * @param string $scopes 空白区切りのスコープ
     * @param ServiceTrust $trust 信頼状態
     * @param bool $skipsConsent 同意画面を省略するか。信頼状態とは別の設定
     * @param bool $canProvision サービスアカウントを扱えるか。信頼状態とは別の設定
     * @param string|null $iconUrl アイコンの URL
     * @param bool $isConfidential secret を持てるクライアントか
     * @return RegisteredClient 平文 secret はここでしか取れない
     */
    public function execute(
        string $name,
        array $names,
        array $redirectUris,
        string $scopes,
        ServiceTrust $trust,
        bool $isConfidential,
        bool $skipsConsent = false,
        bool $canProvision = false,
        ?string $iconUrl = null,
    ): RegisteredClient {
        $secret = $isConfidential ? Str::random(64) : null;

        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => $secret === null ? null : hash('sha256', $secret),
            'name' => $name,
            'names' => $names,
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
            'is_confidential' => $isConfidential,
            'trust' => $trust,
            'skips_consent' => $skipsConsent,
            'can_provision' => $canProvision,
            'icon_url' => $iconUrl,
        ]);

        return new RegisteredClient($client, $secret);
    }
}
