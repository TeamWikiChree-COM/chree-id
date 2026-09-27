<?php
namespace Plugins\Saml;

use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use RuntimeException;
use Throwable;

/**
 * IdP から届いた SAML Response を確かめ、外部アカウントとして読む。
 */
class SamlResponseReader {
    private readonly SamlSettings $settings;

    public function __construct(SamlSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * @param string $samlResponse POST された SAMLResponse (base64)
     * @param string|null $requestId こちらが出した AuthnRequest の ID
     * @return ExternalIdentity
     * @throws RuntimeException 確かめられなかった場合
     */
    public function read(string $samlResponse, ?string $requestId): ExternalIdentity {
        // IdP から勝手に送られてくる応答 (IdP-initiated) は受けない。
        // 誰が始めたログインか分からず、他人のセッションへ差し込まれうる
        if ($requestId === null) throw new RuntimeException('SAML response without a pending request');

        $response = $this->parse($samlResponse);
        if (!$response->isValid($requestId)) throw new RuntimeException('SAML response rejected: ' . $response->getError(false));

        return $this->toIdentity($response);
    }

    /**
     * 外から来た XML を読む境界。壊れた入力は php-saml が色々な例外で投げるので、ここでまとめる。
     *
     * @param string $samlResponse
     * @return Response
     */
    private function parse(string $samlResponse): Response {
        try {
            return new Response(new Settings($this->settings->toArray()), $samlResponse);
        } catch (Throwable $e) {
            throw new RuntimeException('SAML response unreadable', 0, $e);
        }
    }

    /**
     * @param Response $response 検証済みの応答
     * @return ExternalIdentity
     */
    private function toIdentity(Response $response): ExternalIdentity {
        $nameId = (string) $response->getNameId();
        $format = (string) $response->getNameIdFormat();

        // transient はログインのたびに変わるので、同じ人を同じアカウントへ結べない
        if ($nameId === '' || $format === Constants::NAMEID_TRANSIENT) {
            throw new RuntimeException("SAML NameID is unusable as a stable subject (format: {$format})");
        }

        $attributes = $response->getAttributes();
        $email = $this->attribute($attributes, $this->settings->idp('email_attribute'));
        if ($email === null && $format === Constants::NAMEID_EMAIL_ADDRESS) $email = $nameId;

        return new ExternalIdentity(
            SamlIdp::NAME,
            $nameId,
            $email,
            $email !== null && $this->settings->trustsEmail(),
            $this->attribute($attributes, $this->settings->idp('name_attribute')),
        );
    }

    /**
     * @param array<string, list<string>> $attributes
     * @param string $name 属性名。空なら読まない
     * @return string|null
     */
    private function attribute(array $attributes, string $name): ?string {
        if ($name === '') return null;

        $value = $attributes[$name][0] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
