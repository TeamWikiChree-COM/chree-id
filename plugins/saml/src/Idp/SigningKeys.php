<?php
namespace Plugins\Saml\Idp;

use OpenSSLCertificateSigningRequest;
use RuntimeException;

/**
 * ChreeID が SAML の Response に署名する鍵と証明書を作る・取り込む・調べる。
 *
 * artisan コマンドと管理画面の両方から使う。本番はコマンドを叩けないので、画面から同じことができる必要がある。
 */
class SigningKeys {
    private readonly IdpSettings $settings;

    public function __construct(IdpSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * @return array{subject: string, expiresAt: string}|null 鍵が無ければ null
     */
    public function describe(): ?array {
        if (!$this->settings->isConfigured()) return null;

        $parsed = openssl_x509_parse($this->settings->certificate());
        if ($parsed === false) return null;

        return [
            'subject' => (string) ($parsed['subject']['CN'] ?? ''),
            'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', (int) $parsed['validTo_time_t']),
        ];
    }

    /**
     * 鍵と自己署名の証明書を作って置く。
     *
     * @param int $days 証明書の有効日数
     * @throws RuntimeException openssl が使えない、または書き込めない場合
     */
    public function generate(int $days): void {
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
        $this->store($privateKey, $certificate);
    }

    /**
     * 手元で作った鍵と証明書を取り込む。サーバで openssl が使えないとき用。
     *
     * @param string $privateKey PEM
     * @param string $certificate PEM
     * @return bool 対になっていて取り込めたか
     */
    public function import(string $privateKey, string $certificate): bool {
        $key = @openssl_pkey_get_private($privateKey);
        if ($key === false || @openssl_x509_read($certificate) === false) return false;

        // 別々の鍵を組み合わせると、署名しても SP で確かめられない
        if (!openssl_x509_check_private_key($certificate, $key)) return false;

        $this->store($privateKey, $certificate);

        return true;
    }

    /**
     * @param string $privateKey
     * @param string $certificate
     */
    private function store(string $privateKey, string $certificate): void {
        $this->write($this->settings->keyPath(), $privateKey, 0600);
        $this->write($this->settings->certificatePath(), $certificate, 0644);
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
