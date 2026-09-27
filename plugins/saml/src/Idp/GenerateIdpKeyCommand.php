<?php
namespace Plugins\Saml\Idp;

use Illuminate\Console\Command;
use OpenSSLCertificateSigningRequest;
use RuntimeException;

/**
 * ChreeID が SAML の Response に署名する鍵と、自己署名の証明書を作る。
 */
class GenerateIdpKeyCommand extends Command {
    protected $signature = 'saml:idp-key {--force : 既にある鍵を作り直す} {--days=3650 : 証明書の有効日数}';

    protected $description = 'Generate the signing key and certificate for the SAML IdP';

    /**
     * @param IdpSettings $settings
     * @return int
     */
    public function handle(IdpSettings $settings): int {
        // 作り直すと、登録済みの SP すべてで証明書の差し替えが要る。うっかり上書きさせない
        if ($settings->isConfigured() && !$this->option('force')) {
            $this->error('The SAML signing key already exists. Use --force to replace it (every SP must then re-import the metadata).');

            return self::FAILURE;
        }

        [$key, $certificate] = $this->generate((int) $this->option('days'));
        $this->write($settings->keyPath(), $key, 0600);
        $this->write($settings->certificatePath(), $certificate, 0644);

        $this->info("Key: {$settings->keyPath()}");
        $this->info("Certificate: {$settings->certificatePath()}");

        return self::SUCCESS;
    }

    /**
     * @param int $days
     * @return array{string, string} 秘密鍵と証明書 (PEM)
     */
    private function generate(int $days): array {
        $options = ['digest_alg' => 'sha256', 'private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        // Windows の PHP は openssl の設定ファイルが無いとここで失敗する。OPENSSL_CONF を指定すれば通る
        $key = openssl_pkey_new($options);
        if ($key === false) throw new RuntimeException('openssl failed: ' . openssl_error_string());

        $csr = openssl_csr_new(['commonName' => $host === '' ? 'chreeid' : $host], $key, $options);
        if (!$csr instanceof OpenSSLCertificateSigningRequest) throw new RuntimeException('openssl failed: ' . openssl_error_string());

        $cert = openssl_csr_sign($csr, null, $key, $days, $options);
        if ($cert === false) throw new RuntimeException('openssl failed: ' . openssl_error_string());

        openssl_pkey_export($key, $privateKey);
        openssl_x509_export($cert, $certificate);

        return [$privateKey, $certificate];
    }

    /**
     * @param string $path
     * @param string $contents
     * @param int $mode
     */
    private function write(string $path, string $contents, int $mode): void {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException("cannot create {$dir}");
        if (file_put_contents($path, $contents) === false) throw new RuntimeException("cannot write {$path}");

        chmod($path, $mode);
    }
}
