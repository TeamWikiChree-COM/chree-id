<?php
namespace Plugins\Saml\Idp;

use DOMElement;
use DOMXPath;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;

/**
 * SP の AuthnRequest の署名を確かめる。
 */
class RequestSignatureVerifier {
    /** SHA-1 は受けない。衝突が作れるので、署名の意味が無くなる */
    private const ALGORITHMS = [XMLSecurityKey::RSA_SHA256, XMLSecurityKey::RSA_SHA384, XMLSecurityKey::RSA_SHA512];

    /**
     * HTTP-Redirect の署名。クエリ文字列そのものに署名されている。
     *
     * 署名されたのは SP が組んだときの符号化なので、デコードせずに届いたままの文字列で確かめる。
     *
     * @param string $rawQuery 届いたままのクエリ文字列
     * @param string $certificate SP の証明書 (PEM)
     * @return bool
     */
    public function verifyRedirect(string $rawQuery, string $certificate): bool {
        $params = $this->rawParams($rawQuery);
        $algorithm = rawurldecode($params['SigAlg'] ?? '');
        if (!isset($params['SAMLRequest'], $params['Signature']) || !in_array($algorithm, self::ALGORITHMS, true)) return false;

        $signed = 'SAMLRequest=' . $params['SAMLRequest'];
        if (isset($params['RelayState'])) $signed .= '&RelayState=' . $params['RelayState'];
        $signed .= '&SigAlg=' . $params['SigAlg'];

        $key = new XMLSecurityKey($algorithm, ['type' => 'public']);
        $key->loadKey($certificate, false, true);

        return $key->verifySignature($signed, (string) base64_decode(rawurldecode($params['Signature']), true)) === 1;
    }

    /**
     * HTTP-POST の署名。AuthnRequest の中に XML 署名で入っている。
     *
     * 署名が AuthnRequest そのもの (ルート) を指していることまで見る。
     * 別の要素への署名で通すと、署名の外に置いた中身を差し込まれる (署名ラッピング)。
     *
     * @param AuthnRequest $request
     * @param string $certificate SP の証明書 (PEM)
     * @return bool
     */
    public function verifyPost(AuthnRequest $request, string $certificate): bool {
        $root = $request->document->documentElement;
        if (!$root instanceof DOMElement) return false;

        $xpath = new DOMXPath($request->document);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $references = $xpath->query('/*/ds:Signature/ds:SignedInfo/ds:Reference/@URI');
        if ($references === false || $references->length !== 1 || $references->item(0)?->nodeValue !== '#' . $request->id) return false;

        return $this->validate($request, $certificate);
    }

    /**
     * 外から来た XML の署名を確かめる境界。壊れた署名は xmlseclibs が例外で知らせてくる。
     *
     * @param AuthnRequest $request
     * @param string $certificate
     * @return bool
     */
    private function validate(AuthnRequest $request, string $certificate): bool {
        try {
            return Utils::validateSign($request->document, $certificate);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param string $rawQuery
     * @return array<string, string> 名前 => 届いたままの値
     */
    private function rawParams(string $rawQuery): array {
        $params = [];
        foreach (explode('&', $rawQuery) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $params[$name] = $value;
        }

        return $params;
    }
}
