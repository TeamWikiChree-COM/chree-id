<?php
namespace Plugins\Saml\Tests;

use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * テスト用の SP。AuthnRequest を組み立て、ChreeID から返った Response を php-saml で確かめる。
 */
class SamlSpClient {
    public const ENTITY_ID = 'https://sp.example.com/metadata';
    public const ACS_URL = 'https://sp.example.com/acs';

    /** fixtures/ の鍵。ChreeID (IdP) の鍵とは別のものを SP の鍵にする */
    private const KEY = 'other';

    public readonly string $requestId;

    public function __construct() {
        $this->requestId = '_req' . bin2hex(random_bytes(8));
    }

    /**
     * @param string|null $acsUrl 要求に書く ACS。null なら書かない
     * @param string $issuer
     * @return string AuthnRequest の XML
     */
    public function authnRequest(?string $acsUrl = self::ACS_URL, string $issuer = self::ENTITY_ID): string {
        $acs = $acsUrl === null ? '' : ' AssertionConsumerServiceURL="' . $acsUrl . '"';

        return '<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"'
            . ' ID="' . $this->requestId . '" Version="2.0" IssueInstant="' . gmdate('Y-m-d\TH:i:s\Z') . '"'
            . ' ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"' . $acs . '>'
            . '<saml:Issuer>' . $issuer . '</saml:Issuer>'
            . '</samlp:AuthnRequest>';
    }

    /**
     * HTTP-Redirect のクエリ文字列。
     *
     * @param string $xml
     * @param string $relayState
     * @param string|null $signWith fixtures/ の鍵の名前。null なら署名しない
     * @return string
     */
    public function redirectQuery(string $xml, string $relayState, ?string $signWith = null): string {
        $query = 'SAMLRequest=' . rawurlencode(base64_encode((string) gzdeflate($xml)))
            . '&RelayState=' . rawurlencode($relayState);
        if ($signWith === null) return $query;

        $query .= '&SigAlg=' . rawurlencode(XMLSecurityKey::RSA_SHA256);
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($this->fixture("{$signWith}.key"));

        return $query . '&Signature=' . rawurlencode(base64_encode($key->signData($query)));
    }

    /**
     * @return string SP の証明書 (PEM)
     */
    public function certificate(): string {
        return $this->fixture(self::KEY . '.crt');
    }

    /**
     * php-saml を SP として使い、Response を確かめる。
     *
     * php-saml は Destination を $_SERVER から組んだ「今の URL」と照らすので、ACS に居るように見せる。
     *
     * @param string $samlResponse base64
     * @param string $idpEntityId
     * @param string $idpCertificate
     * @return Response 確かめ済みの Response。通らなければ getError() に理由が入る
     */
    public function receive(string $samlResponse, string $idpEntityId, string $idpCertificate): Response {
        $_SERVER['HTTP_HOST'] = 'sp.example.com';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['REQUEST_URI'] = '/acs';
        unset($_SERVER['QUERY_STRING']);

        return new Response(new Settings([
            'strict' => true,
            'sp' => ['entityId' => self::ENTITY_ID, 'assertionConsumerService' => ['url' => self::ACS_URL]],
            'idp' => ['entityId' => $idpEntityId, 'singleSignOnService' => ['url' => 'https://idp.invalid/sso'], 'x509cert' => $idpCertificate],
            'security' => ['wantAssertionsSigned' => true],
        ]), $samlResponse);
    }

    /**
     * @param string $name
     * @return string
     */
    private function fixture(string $name): string {
        return (string) file_get_contents(__DIR__ . "/fixtures/{$name}");
    }
}
