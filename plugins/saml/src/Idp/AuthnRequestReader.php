<?php
namespace Plugins\Saml\Idp;

use DOMDocument;
use DOMElement;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Utils;
use RuntimeException;

/**
 * SP から届いた SAMLRequest を読む。
 */
class AuthnRequestReader {
    /** これより大きい要求は読まない。展開して膨らませる攻撃を防ぐ */
    private const MAX_BYTES = 64 * 1024;

    /**
     * @param string $samlRequest 届いた SAMLRequest (base64)
     * @param bool $deflated HTTP-Redirect なら圧縮されている
     * @return AuthnRequest
     * @throws RuntimeException 読めない場合
     */
    public function read(string $samlRequest, bool $deflated): AuthnRequest {
        $xml = $this->decode($samlRequest, $deflated);

        // DOCTYPE を拒む読み方。外部実体の読み込み (XXE) を許さない
        $document = new DOMDocument();
        if (Utils::loadXML($document, $xml) === false) throw new RuntimeException('SAMLRequest is not valid XML');

        $root = $document->documentElement;
        if (!$root instanceof DOMElement || $root->namespaceURI !== Constants::NS_SAMLP || $root->localName !== 'AuthnRequest') {
            throw new RuntimeException('SAMLRequest is not an AuthnRequest');
        }

        $binding = $root->getAttribute('ProtocolBinding');
        if ($binding !== '' && $binding !== Constants::BINDING_HTTP_POST) throw new RuntimeException("unsupported response binding: {$binding}");

        $id = $root->getAttribute('ID');
        $issuer = trim((string) $root->getElementsByTagNameNS(Constants::NS_SAML, 'Issuer')->item(0)?->textContent);
        if ($id === '' || $issuer === '') throw new RuntimeException('AuthnRequest without ID or Issuer');

        $acsUrl = $root->getAttribute('AssertionConsumerServiceURL');

        return new AuthnRequest($id, $issuer, $acsUrl === '' ? null : $acsUrl, $document);
    }

    /**
     * @param string $samlRequest
     * @param bool $deflated
     * @return string
     */
    private function decode(string $samlRequest, bool $deflated): string {
        if (strlen($samlRequest) > self::MAX_BYTES) throw new RuntimeException('SAMLRequest too large');

        $decoded = base64_decode($samlRequest, true);
        if ($decoded === false) throw new RuntimeException('SAMLRequest is not base64');
        if (!$deflated) return $decoded;

        $inflated = @gzinflate($decoded, self::MAX_BYTES * 4);
        if ($inflated === false) throw new RuntimeException('SAMLRequest cannot be inflated');

        return $inflated;
    }
}
