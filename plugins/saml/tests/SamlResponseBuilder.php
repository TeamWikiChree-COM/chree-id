<?php
namespace Plugins\Saml\Tests;

use DOMDocument;
use DOMElement;
use Plugins\Saml\XmlSigner;

/**
 * テスト用の IdP。鍵を作り、署名した SAML Response を組み立てる。
 */
class SamlResponseBuilder {
    public const IDP_ENTITY_ID = 'https://idp.example.com/metadata';

    public readonly string $privateKey;
    public readonly string $certificate;

    /**
     * 鍵はテストのたびに作らず、fixtures/ に置いたものを使う。Windows の PHP は openssl の設定が無く作れないことがある。
     *
     * @param string $name fixtures/ の鍵の名前 (idp か other)
     */
    public function __construct(string $name = 'idp') {
        $this->privateKey = (string) file_get_contents(__DIR__ . "/fixtures/{$name}.key");
        $this->certificate = (string) file_get_contents(__DIR__ . "/fixtures/{$name}.crt");
    }

    /**
     * @param array{requestId: string, nameId: string, acs: string, audience: string, attributes?: array<string, string>, signed?: bool, nameIdFormat?: string} $o
     * @return string base64 した Response
     */
    public function build(array $o): string {
        $assertion = $this->assertion($o);
        if ($o['signed'] ?? true) $assertion = $this->sign($assertion);

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $xml = '<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"'
            . ' ID="_r' . bin2hex(random_bytes(8)) . '" Version="2.0" IssueInstant="' . $now . '"'
            . ' Destination="' . $o['acs'] . '" InResponseTo="' . $o['requestId'] . '">'
            . '<saml:Issuer>' . self::IDP_ENTITY_ID . '</saml:Issuer>'
            . '<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>'
            . $assertion
            . '</samlp:Response>';

        return base64_encode($xml);
    }

    /**
     * @param array<string, mixed> $o
     * @return string
     */
    private function assertion(array $o): string {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $before = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $after = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
        $format = $o['nameIdFormat'] ?? 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress';

        $attributes = '';
        foreach ($o['attributes'] ?? [] as $name => $value) {
            $attributes .= '<saml:Attribute Name="' . $name . '"><saml:AttributeValue>' . $value . '</saml:AttributeValue></saml:Attribute>';
        }

        return '<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_a' . bin2hex(random_bytes(8)) . '" Version="2.0" IssueInstant="' . $now . '">'
            . '<saml:Issuer>' . self::IDP_ENTITY_ID . '</saml:Issuer>'
            . '<saml:Subject><saml:NameID Format="' . $format . '">' . $o['nameId'] . '</saml:NameID>'
            . '<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">'
            . '<saml:SubjectConfirmationData InResponseTo="' . $o['requestId'] . '" Recipient="' . $o['acs'] . '" NotOnOrAfter="' . $after . '"/>'
            . '</saml:SubjectConfirmation></saml:Subject>'
            . '<saml:Conditions NotBefore="' . $before . '" NotOnOrAfter="' . $after . '">'
            . '<saml:AudienceRestriction><saml:Audience>' . $o['audience'] . '</saml:Audience></saml:AudienceRestriction></saml:Conditions>'
            . '<saml:AuthnStatement AuthnInstant="' . $now . '" SessionIndex="_s1"><saml:AuthnContext>'
            . '<saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:Password</saml:AuthnContextClassRef>'
            . '</saml:AuthnContext></saml:AuthnStatement>'
            . ($attributes === '' ? '' : '<saml:AttributeStatement>' . $attributes . '</saml:AttributeStatement>')
            . '</saml:Assertion>';
    }

    /**
     * @param string $assertion
     * @return string 署名入りの Assertion (XML 宣言なし)
     */
    private function sign(string $assertion): string {
        $doc = new DOMDocument();
        $doc->loadXML($assertion);
        $root = $doc->documentElement;
        assert($root instanceof DOMElement);

        (new XmlSigner())->sign($root, $this->privateKey, $this->certificate);

        return (string) $doc->saveXML($root);
    }
}
