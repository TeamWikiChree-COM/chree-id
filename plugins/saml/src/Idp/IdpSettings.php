<?php
namespace Plugins\Saml\Idp;

use RuntimeException;

/**
 * ChreeID が SAML IdP として名乗る値と、署名の鍵。
 *
 * 鍵は OIDC の署名鍵と分けている。SAML では証明書が SP 側に登録されるので、
 * OIDC の鍵を入れ替えるたびに SP の設定まで直させることになるため。
 */
class IdpSettings {
    /**
     * @return string IdP の entityID。メタデータの URL をそのまま使う
     */
    public function entityId(): string {
        return url('/plugins/saml/idp/metadata');
    }

    /**
     * @return string SSO の受け口。HTTP-Redirect と HTTP-POST の両方で受ける
     */
    public function ssoUrl(): string {
        return url('/plugins/saml/idp/sso');
    }

    /**
     * @return string
     */
    public function keyPath(): string {
        return (string) config('saml.signing.key_path');
    }

    /**
     * @return string
     */
    public function certificatePath(): string {
        return (string) config('saml.signing.certificate_path');
    }

    /**
     * @return bool 署名の鍵が置いてあるか
     */
    public function isConfigured(): bool {
        return is_file($this->keyPath()) && is_file($this->certificatePath());
    }

    /**
     * @return string PEM
     * @throws RuntimeException 鍵が無い場合
     */
    public function privateKey(): string {
        return $this->read($this->keyPath());
    }

    /**
     * @return string PEM
     * @throws RuntimeException 証明書が無い場合
     */
    public function certificate(): string {
        return $this->read($this->certificatePath());
    }

    /**
     * @param string $path
     * @return string
     */
    private function read(string $path): string {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) throw new RuntimeException("SAML signing file not found: {$path}. Run php artisan saml:idp-key");

        return $contents;
    }
}
