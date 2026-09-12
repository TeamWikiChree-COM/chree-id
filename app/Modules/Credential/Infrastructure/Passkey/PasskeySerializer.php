<?php
namespace App\Modules\Credential\Infrastructure\Passkey;

use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

/**
 * WebAuthn のオブジェクトと JSON の相互変換。
 *
 * v5 からライブラリ側が Symfony Serializer を使う形になったので、その組み立てをここに閉じる。
 */
class PasskeySerializer {
    private ?SerializerInterface $serializer = null;

    /**
     * ブラウザが返した credential を読む
     *
     * @param string $json
     * @return PublicKeyCredential
     */
    public function toCredential(string $json): PublicKeyCredential {
        $credential = $this->serializer()->deserialize($json, PublicKeyCredential::class, 'json');

        return $credential;
    }

    /**
     * ブラウザへ渡すチャレンジ。challenge や user.id は生のバイナリなので、
     * そのまま json_encode すると壊れる。ライブラリ側の変換を通す。
     *
     * @param PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options
     * @return string
     */
    public function encodeOptions(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string {
        return $this->serializer()->serialize($options, 'json');
    }

    /**
     * 預けておいたチャレンジを読み戻す。
     *
     * @param string $json encodeOptions() が返した JSON
     * @return PublicKeyCredentialCreationOptions
     */
    public function decodeCreationOptions(string $json): PublicKeyCredentialCreationOptions {
        $options = $this->serializer()->deserialize($json, PublicKeyCredentialCreationOptions::class, 'json');

        return $options;
    }

    /**
     * @param PublicKeyCredentialSource $source
     * @return string DBに入れる JSON
     */
    public function encodeSource(PublicKeyCredentialSource $source): string {
        return $this->serializer()->serialize($source, 'json');
    }

    /**
     * @param string $json
     * @return PublicKeyCredentialSource
     */
    public function decodeSource(string $json): PublicKeyCredentialSource {
        $source = $this->serializer()->deserialize($json, PublicKeyCredentialSource::class, 'json');

        return $source;
    }

    /**
     * @return SerializerInterface
     */
    private function serializer(): SerializerInterface {
        if ($this->serializer !== null) return $this->serializer;

        $manager = AttestationStatementSupportManager::create();

        // 端末の出自を証明する attestation は使わない。
        // 検証には端末メーカーのルート証明書が要り、利便性の割に得るものが少ない
        $manager->add(NoneAttestationStatementSupport::create());

        return $this->serializer = (new WebauthnSerializerFactory($manager))->create();
    }
}
