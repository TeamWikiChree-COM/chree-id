<?php
namespace Plugins\Saml;

use DOMElement;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * SAML の要素 (Assertion など) に XML 署名を入れる。
 */
class XmlSigner {
    private const SAML_NS = 'urn:oasis:names:tc:SAML:2.0:assertion';

    /**
     * 署名は要素の ID を参照し、Issuer の直後に置く。SAML のスキーマがその位置を決めている。
     *
     * @param DOMElement $element ID 属性と Issuer を持つ要素
     * @param string $privateKey PEM
     * @param string $certificate PEM
     */
    public function sign(DOMElement $element, string $privateKey, string $certificate): void {
        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $dsig->addReference(
            $element,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N],
            ['id_name' => 'ID', 'overwrite' => false],
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey);
        $dsig->sign($key);
        $dsig->add509Cert($certificate);

        $issuer = $element->getElementsByTagNameNS(self::SAML_NS, 'Issuer')->item(0);
        $dsig->insertSignature($element, $issuer?->nextSibling);
    }
}
