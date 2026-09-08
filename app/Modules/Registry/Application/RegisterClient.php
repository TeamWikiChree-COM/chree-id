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
     * @param string $name サービス名
     * @param list<string> $redirectUris 許可するリダイレクト先
     * @param string $scopes 空白区切りのスコープ
     * @param ServiceTrust $trust 信頼状態
     * @param bool $isConfidential secret を持てるクライアントか
     * @return RegisteredClient 平文 secret はここでしか取れない
     */
    public function execute(
        string $name,
        array $redirectUris,
        string $scopes,
        ServiceTrust $trust,
        bool $isConfidential,
    ): RegisteredClient {
        $secret = $isConfidential ? Str::random(64) : null;

        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => $secret === null ? null : hash('sha256', $secret),
            'name' => $name,
            'redirect_uris' => $redirectUris,
            'scopes' => $scopes,
            'is_confidential' => $isConfidential,
            'trust' => $trust,
        ]);

        return new RegisteredClient($client, $secret);
    }
}
