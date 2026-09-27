<?php
namespace Plugins\Saml\Idp;

use DOMDocument;

/**
 * SP から届いた AuthnRequest のうち、使う値。
 */
readonly class AuthnRequest {
    public string $id;
    public string $issuer;
    public ?string $acsUrl;
    public DOMDocument $document;

    /**
     * @param string $id Response の InResponseTo に返す
     * @param string $issuer SP の entityID
     * @param string|null $acsUrl 指定されていれば、登録した ACS と一致しなければならない
     * @param DOMDocument $document 署名を確かめるために元の XML も持つ
     */
    public function __construct(string $id, string $issuer, ?string $acsUrl, DOMDocument $document) {
        $this->id = $id;
        $this->issuer = $issuer;
        $this->acsUrl = $acsUrl;
        $this->document = $document;
    }
}
