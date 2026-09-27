<?php
namespace Plugins\Saml\Idp;

use Illuminate\Console\Command;

/**
 * 登録済みのサービス (oauth_clients) に、SAML でつなぐ設定を追加する。
 *
 * サービスそのもの (名前、信頼状態、同意の省略) は本体の管理画面か chreeid:register-client で作っておく。
 * 本番でコマンドを叩けないときは、管理画面 (/plugins/saml/admin) から同じことができる。
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
     * @param ServiceProviders $providers
     * @return int
     */
    public function handle(ServiceProviders $providers): int {
        $certificatePath = (string) $this->option('certificate');
        $certificate = $certificatePath === '' ? '' : (string) @file_get_contents($certificatePath);

        $input = [
            'client_id' => (string) $this->argument('client_id'),
            'entity_id' => (string) $this->argument('entity_id'),
            'acs_url' => (string) $this->argument('acs_url'),
            'certificate' => $certificate,
            'scopes' => (string) $this->option('scopes'),
        ];

        $current = ServiceProviderModel::query()->where('client_id', $input['client_id'])->first();
        $errors = $providers->check($input, $current);
        foreach ($errors as $field => $code) $this->error("{$field}: {$code}");
        if ($errors !== []) return self::FAILURE;

        $providers->save($input, $current);
        $this->info("Connected {$input['client_id']}. IdP metadata: " . url('/plugins/saml/idp/metadata'));

        return self::SUCCESS;
    }
}
