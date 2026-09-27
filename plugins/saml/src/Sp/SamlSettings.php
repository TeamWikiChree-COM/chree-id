<?php
namespace Plugins\Saml\Sp;

use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Settings;

/**
 * config('saml.…') を php-saml の設定に組み立てる。
 */
class SamlSettings {
    /**
     * @return bool IdP の接続に要る値が揃っているか
     */
    public function isConfigured(): bool {
        return $this->idp('entity_id') !== '' && $this->idp('sso_url') !== '' && $this->idp('x509cert') !== '';
    }

    /**
     * メタデータだけを出すための設定。IdP が未設定でも SP の情報は渡せるようにする。
     *
     * IdP の登録には SP のメタデータが先に要るので、IdP 側の値が揃う前から出せないと手順が詰まる。
     *
     * @return Settings
     */
    public function buildSpOnly(): Settings {
        return new Settings($this->toArray(), true);
    }

    /**
     * @return string SP の entityID
     */
    public function spEntityId(): string {
        $configured = (string) config('saml.sp.entity_id', '');

        return $configured !== '' ? $configured : url('/plugins/saml/metadata');
    }

    /**
     * @param string $key
     * @return string
     */
    public function idp(string $key): string {
        return (string) config('saml.idp.' . $key, '');
    }

    /**
     * 社名などの固有名なので、言語ごとには分けない。
     *
     * @return array<string, string> ロケール => 名前
     */
    public function label(): array {
        $label = $this->idp('label');
        if ($label === '') $label = 'SAML';

        return ['ja' => $label, 'en' => $label];
    }

    /**
     * @return bool IdP のメールを確かめ済みとして扱うか
     */
    public function trustsEmail(): bool {
        return (bool) config('saml.idp.trust_email', false);
    }

    /**
     * @return array<string, mixed> php-saml の Auth、Settings に渡す形
     */
    public function toArray(): array {
        $cert = (string) config('saml.sp.x509cert', '');
        $key = (string) config('saml.sp.private_key', '');
        $signs = $cert !== '' && $key !== '';

        return [
            // 署名、宛先、有効期限の確認を省かせない
            'strict' => true,
            'sp' => [
                'entityId' => $this->spEntityId(),
                'assertionConsumerService' => ['url' => url('/plugins/saml/acs'), 'binding' => Constants::BINDING_HTTP_POST],
                'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
                'x509cert' => $cert,
                'privateKey' => $key,
            ],
            'idp' => [
                'entityId' => $this->idp('entity_id'),
                'singleSignOnService' => ['url' => $this->idp('sso_url'), 'binding' => Constants::BINDING_HTTP_REDIRECT],
                'x509cert' => $this->idp('x509cert'),
            ],
            'security' => [
                'authnRequestsSigned' => $signs,
                // Response だけ署名する IdP もあるが、Assertion の署名を求めておかないと
                // 署名の外に置いた Assertion を差し込まれる (署名ラッピング) 余地が増える
                'wantAssertionsSigned' => true,
                'wantNameId' => true,
                'requestedAuthnContext' => false,
                'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
                'rejectDeprecatedAlgorithm' => true,
            ],
        ];
    }
}
