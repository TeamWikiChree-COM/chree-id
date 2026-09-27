<?php
namespace Plugins\Saml\Idp;

use App\Modules\Provider\Application\SignedInService;
use DOMDocument;
use DOMElement;
use OneLogin\Saml2\Constants;
use Plugins\Saml\XmlSigner;

/**
 * SP へ返す、署名した SAML Response を組み立てる。
 *
 * 利用者の名前などが値に入るので、文字列をつなげず DOM で組む。つなげると値の < や & で XML が壊れる。
 */
class ResponseBuilder {
    /** Assertion を受け取ってよい期間 (秒)。SP へ届くまでの時間だけあればよい */
    private const LIFETIME = 300;

    /** SP と時計がずれていても受け取れるように、有効期間の始まりを少し前にする (秒) */
    private const CLOCK_SKEW = 60;

    private readonly IdpSettings $settings;
    private readonly XmlSigner $signer;

    public function __construct(IdpSettings $settings, XmlSigner $signer) {
        $this->settings = $settings;
        $this->signer = $signer;
    }

    /**
     * @param SignedInService $signIn 本体が決めた sub と属性
     * @param ServiceProviderModel $sp
     * @param string $inResponseTo AuthnRequest の ID
     * @param string $acsUrl 送り先
     * @return string base64 した Response
     */
    public function build(SignedInService $signIn, ServiceProviderModel $sp, string $inResponseTo, string $acsUrl): string {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $response = $this->samlp($doc, 'Response');
        $response->setAttribute('ID', $this->newId());
        $response->setAttribute('Version', '2.0');
        $response->setAttribute('IssueInstant', $this->time(0));
        $response->setAttribute('Destination', $acsUrl);
        $response->setAttribute('InResponseTo', $inResponseTo);
        $doc->appendChild($response);

        $response->appendChild($this->issuer($doc));
        $status = $this->samlp($doc, 'Status');
        $code = $this->samlp($doc, 'StatusCode');
        $code->setAttribute('Value', Constants::STATUS_SUCCESS);
        $status->appendChild($code);
        $response->appendChild($status);

        $assertion = $this->assertion($doc, $signIn, $sp, $inResponseTo, $acsUrl);
        $response->appendChild($assertion);
        $this->signer->sign($assertion, $this->settings->privateKey(), $this->settings->certificate());

        return base64_encode((string) $doc->saveXML());
    }

    /**
     * @param DOMDocument $doc
     * @param SignedInService $signIn
     * @param ServiceProviderModel $sp
     * @param string $inResponseTo
     * @param string $acsUrl
     * @return DOMElement
     */
    private function assertion(DOMDocument $doc, SignedInService $signIn, ServiceProviderModel $sp, string $inResponseTo, string $acsUrl): DOMElement {
        $assertion = $this->saml($doc, 'Assertion');
        $assertion->setAttribute('ID', $this->newId());
        $assertion->setAttribute('Version', '2.0');
        $assertion->setAttribute('IssueInstant', $this->time(0));

        $assertion->appendChild($this->issuer($doc));
        $assertion->appendChild($this->subject($doc, $signIn->subject, $sp->entity_id, $inResponseTo, $acsUrl));
        $assertion->appendChild($this->conditions($doc, $sp->entity_id));
        $assertion->appendChild($this->authnStatement($doc));

        $attributes = $this->attributes($doc, $signIn->claims);
        if ($attributes !== null) $assertion->appendChild($attributes);

        return $assertion;
    }

    /**
     * NameID は persistent で sub を出す。OIDC で入ったときと同じ値になり、SP 側で同じ人として扱える。
     *
     * @param DOMDocument $doc
     * @param string $sub
     * @param string $spEntityId
     * @param string $inResponseTo
     * @param string $acsUrl
     * @return DOMElement
     */
    private function subject(DOMDocument $doc, string $sub, string $spEntityId, string $inResponseTo, string $acsUrl): DOMElement {
        $subject = $this->saml($doc, 'Subject');

        $nameId = $this->saml($doc, 'NameID', $sub);
        $nameId->setAttribute('Format', Constants::NAMEID_PERSISTENT);
        $nameId->setAttribute('NameQualifier', $this->settings->entityId());
        $nameId->setAttribute('SPNameQualifier', $spEntityId);
        $subject->appendChild($nameId);

        $confirmation = $this->saml($doc, 'SubjectConfirmation');
        $confirmation->setAttribute('Method', Constants::CM_BEARER);
        $data = $this->saml($doc, 'SubjectConfirmationData');
        $data->setAttribute('InResponseTo', $inResponseTo);
        $data->setAttribute('Recipient', $acsUrl);
        $data->setAttribute('NotOnOrAfter', $this->time(self::LIFETIME));
        $confirmation->appendChild($data);
        $subject->appendChild($confirmation);

        return $subject;
    }

    /**
     * @param DOMDocument $doc
     * @param string $audience
     * @return DOMElement
     */
    private function conditions(DOMDocument $doc, string $audience): DOMElement {
        $conditions = $this->saml($doc, 'Conditions');
        $conditions->setAttribute('NotBefore', $this->time(-self::CLOCK_SKEW));
        $conditions->setAttribute('NotOnOrAfter', $this->time(self::LIFETIME));

        $restriction = $this->saml($doc, 'AudienceRestriction');
        $restriction->appendChild($this->saml($doc, 'Audience', $audience));
        $conditions->appendChild($restriction);

        return $conditions;
    }

    /**
     * @param DOMDocument $doc
     * @return DOMElement
     */
    private function authnStatement(DOMDocument $doc): DOMElement {
        $statement = $this->saml($doc, 'AuthnStatement');
        $statement->setAttribute('AuthnInstant', $this->time(0));
        $statement->setAttribute('SessionIndex', $this->newId());

        $context = $this->saml($doc, 'AuthnContext');
        $context->appendChild($this->saml($doc, 'AuthnContextClassRef', Constants::AC_UNSPECIFIED));
        $statement->appendChild($context);

        return $statement;
    }

    /**
     * 属性の名前は OIDC のクレーム名をそのまま使う (email、name など)。
     *
     * @param DOMDocument $doc
     * @param array<string, mixed> $claims
     * @return DOMElement|null 渡す属性が無ければ null
     */
    private function attributes(DOMDocument $doc, array $claims): ?DOMElement {
        $statement = $this->saml($doc, 'AttributeStatement');
        foreach ($claims as $name => $value) {
            $values = $this->attributeValues($value);
            if ($values === []) continue;

            $attribute = $this->saml($doc, 'Attribute');
            $attribute->setAttribute('Name', $name);
            $attribute->setAttribute('NameFormat', Constants::ATTRNAME_FORMAT_BASIC);
            foreach ($values as $v) $attribute->appendChild($this->saml($doc, 'AttributeValue', $v));
            $statement->appendChild($attribute);
        }

        return $statement->hasChildNodes() ? $statement : null;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function attributeValues(mixed $value): array {
        if ($value === null) return [];
        if (is_bool($value)) return [$value ? 'true' : 'false'];
        if (is_scalar($value)) return [(string) $value];
        if (!is_array($value)) return [];

        return array_merge(...array_map($this->attributeValues(...), array_values($value)));
    }

    /**
     * @param DOMDocument $doc
     * @return DOMElement
     */
    private function issuer(DOMDocument $doc): DOMElement {
        return $this->saml($doc, 'Issuer', $this->settings->entityId());
    }

    /**
     * @param DOMDocument $doc
     * @param string $name
     * @param string|null $text 入れる文字列。createElementNS の値と違い、& などをそのまま扱える
     * @return DOMElement
     */
    private function saml(DOMDocument $doc, string $name, ?string $text = null): DOMElement {
        $element = $doc->createElementNS(Constants::NS_SAML, 'saml:' . $name);
        if ($text !== null) $element->appendChild($doc->createTextNode($text));

        return $element;
    }

    /**
     * @param DOMDocument $doc
     * @param string $name
     * @return DOMElement
     */
    private function samlp(DOMDocument $doc, string $name): DOMElement {
        return $doc->createElementNS(Constants::NS_SAMLP, 'samlp:' . $name);
    }

    /**
     * @return string XML の ID。数字で始められないので接頭辞を付ける
     */
    private function newId(): string {
        return '_' . bin2hex(random_bytes(20));
    }

    /**
     * @param int $offset 今からの秒数
     * @return string
     */
    private function time(int $offset): string {
        return gmdate('Y-m-d\TH:i:s\Z', time() + $offset);
    }
}
