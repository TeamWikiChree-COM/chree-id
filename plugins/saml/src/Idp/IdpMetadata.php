<?php
namespace Plugins\Saml\Idp;

use DOMDocument;
use DOMElement;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Utils;

/**
 * SP に登録してもらう、IdP としての ChreeID のメタデータ。
 */
class IdpMetadata {
    private const NS_MD = 'urn:oasis:names:tc:SAML:2.0:metadata';
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private readonly IdpSettings $settings;

    public function __construct(IdpSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * @return string
     */
    public function xml(): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $entity = $doc->createElementNS(self::NS_MD, 'md:EntityDescriptor');
        $entity->setAttribute('entityID', $this->settings->entityId());
        $doc->appendChild($entity);

        $idp = $doc->createElementNS(self::NS_MD, 'md:IDPSSODescriptor');
        // SP の AuthnRequest に署名を求めるかは SP ごとに決める (証明書を登録したものだけ確かめる)
        $idp->setAttribute('WantAuthnRequestsSigned', 'false');
        $idp->setAttribute('protocolSupportEnumeration', Constants::NS_SAMLP);
        $entity->appendChild($idp);

        $idp->appendChild($this->keyDescriptor($doc));
        $idp->appendChild($doc->createElementNS(self::NS_MD, 'md:NameIDFormat', Constants::NAMEID_PERSISTENT));
        foreach ([Constants::BINDING_HTTP_REDIRECT, Constants::BINDING_HTTP_POST] as $binding) {
            $sso = $doc->createElementNS(self::NS_MD, 'md:SingleSignOnService');
            $sso->setAttribute('Binding', $binding);
            $sso->setAttribute('Location', $this->settings->ssoUrl());
            $idp->appendChild($sso);
        }

        return (string) $doc->saveXML();
    }

    /**
     * @param DOMDocument $doc
     * @return DOMElement
     */
    private function keyDescriptor(DOMDocument $doc): DOMElement {
        $descriptor = $doc->createElementNS(self::NS_MD, 'md:KeyDescriptor');
        $descriptor->setAttribute('use', 'signing');

        $info = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $data = $doc->createElementNS(self::NS_DS, 'ds:X509Data');
        // PEM の BEGIN/END の行と改行を除いた本体だけを載せる
        $body = Utils::formatCert($this->settings->certificate(), false);
        $data->appendChild($doc->createElementNS(self::NS_DS, 'ds:X509Certificate', $body));
        $info->appendChild($data);
        $descriptor->appendChild($info);

        return $descriptor;
    }
}
