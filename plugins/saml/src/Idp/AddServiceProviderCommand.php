<?php
namespace Plugins\Saml\Idp;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Console\Command;

/**
 * 登録済みのサービス (oauth_clients) に、SAML でつなぐ設定を足す。
 *
 * サービスそのもの (名前・信頼状態・同意の省略) は本体の管理画面か chreeid:register-client で作っておく。
 */
class AddServiceProviderCommand extends Command {
    protected $signature = 'saml:sp-add
        {client_id : 対応するサービスの client_id}
        {entity_id : SP の entityID}
        {acs_url : SP の ACS の URL (HTTP-POST)}
        {--certificate= : SP の証明書 (PEM) のファイル。指定すると AuthnRequest の署名を必ず確かめる}
        {--scopes=openid email profile : 渡す属性の範囲}';

    protected $description = 'Connect a registered service to the SAML IdP';

    /**
     * @return int
     */
    public function handle(): int {
        $clientId = (string) $this->argument('client_id');
        $client = OAuthClientModel::query()->find($clientId);
        if ($client === null) return $this->reject("Unknown client: {$clientId}");

        $scopes = array_values(array_filter(explode(' ', (string) $this->option('scopes'))));
        // 本体はサービスに許した範囲の外を頼まれると断る。ログインのたびに落ちる前に、ここで止める
        if (!$client->allowsScopes($scopes)) return $this->reject('The service does not allow these scopes: ' . implode(' ', $scopes));

        $certificate = $this->certificate();
        if ($certificate === false) return $this->reject('Cannot read the certificate file');

        ServiceProviderModel::query()->updateOrCreate(['client_id' => $clientId], [
            'entity_id' => (string) $this->argument('entity_id'),
            'acs_url' => (string) $this->argument('acs_url'),
            'certificate' => $certificate,
            'scopes' => implode(' ', $scopes),
        ]);

        $this->info("Connected {$clientId}. IdP metadata: " . url('/plugins/saml/idp/metadata'));

        return self::SUCCESS;
    }

    /**
     * @return string|false|null 指定が無ければ null、読めなければ false
     */
    private function certificate(): string|false|null {
        $path = $this->option('certificate');
        if (!is_string($path) || $path === '') return null;

        return is_file($path) ? file_get_contents($path) : false;
    }

    /**
     * @param string $message
     * @return int
     */
    private function reject(string $message): int {
        $this->error($message);

        return self::FAILURE;
    }
}
