<?php
namespace Plugins\Saml\Idp;

use Illuminate\Console\Command;

/**
 * ChreeID が SAML の Response に署名する鍵と、自己署名の証明書を作る。
 *
 * 本番でコマンドを叩けないときは、管理画面 (/plugins/saml/admin) から同じことができる。
 */
class GenerateIdpKeyCommand extends Command {
    protected $signature = 'saml:idp-key {--force : 既にある鍵を作り直す} {--days=3650 : 証明書の有効日数}';

    protected $description = 'Generate the signing key and certificate for the SAML IdP';

    /**
     * @param IdpSettings $settings
     * @param SigningKeys $keys
     * @return int
     */
    public function handle(IdpSettings $settings, SigningKeys $keys): int {
        // 作り直すと、登録済みの SP すべてで証明書の差し替えが要る。うっかり上書きさせない
        if ($settings->isConfigured() && !$this->option('force')) {
            $this->error('The SAML signing key already exists. Use --force to replace it (every SP must then re-import the metadata).');

            return self::FAILURE;
        }

        $keys->generate((int) $this->option('days'));

        $this->info("Key: {$settings->keyPath()}");
        $this->info("Certificate: {$settings->certificatePath()}");

        return self::SUCCESS;
    }
}
