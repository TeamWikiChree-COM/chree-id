<?php
namespace Plugins\YahooJapan;

use App\Modules\ExternalLogin\Domain\CodeExchangeIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdpDisplay;
use App\Modules\ExternalLogin\Infrastructure\IdTokenClaims;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Yahoo! JAPAN ID でのログイン (YConnect v2、ChreeID が RP 側)。
 *
 * id_token にはメールが載らないので、UserInfo API から取る。
 */
class YahooJapanIdp implements CodeExchangeIdp {
    public const NAME = 'yahoo-japan';

    private const AUTHORIZE_URL = 'https://auth.login.yahoo.co.jp/yconnect/v2/authorization';
    private const TOKEN_URL = 'https://auth.login.yahoo.co.jp/yconnect/v2/token';
    private const USERINFO_URL = 'https://userinfo.yahooapis.jp/yconnect/v2/attribute';
    private const ISSUER = 'https://auth.login.yahoo.co.jp/yconnect/v2';

    private readonly IdTokenClaims $idTokens;

    public function __construct(IdTokenClaims $idTokens) {
        $this->idTokens = $idTokens;
    }

    /**
     * @return string
     */
    public function name(): string {
        return self::NAME;
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * @return ExternalIdpDisplay
     */
    public function display(): ExternalIdpDisplay {
        return ExternalIdpDisplay::brand('Yahoo! JAPAN', 'yahoo');
    }

    /**
     * @param string $state
     * @param string $nonce
     * @return string
     */
    public function authorizationUrl(string $state, string $nonce): string {
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    /**
     * @param string $code
     * @param string $nonce
     * @return ExternalIdentity
     * @throws RuntimeException 交換や検証に失敗した場合
     */
    public function exchange(string $code, string $nonce): ExternalIdentity {
        // クライアント認証は Basic を使う。YConnect v2 が推奨している方式
        $response = Http::asForm()->withBasicAuth($this->clientId(), $this->clientSecret())->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);
        if (!$response->successful()) throw new RuntimeException('Yahoo! JAPAN とのトークン交換に失敗しました: ' . $response->status());

        $idToken = $response->json('id_token');
        $accessToken = $response->json('access_token');
        if (!is_string($idToken) || !is_string($accessToken)) throw new RuntimeException('id_token か access_token が返りませんでした');

        $claims = $this->idTokens->read($idToken, [self::ISSUER], $this->clientId(), $nonce);

        return $this->identity((string) $claims['sub'], $accessToken);
    }

    /**
     * UserInfo からメールと名前を取る。sub は id_token のものを正とし、食い違えば受けない。
     *
     * @param string $subject id_token の sub
     * @param string $accessToken
     * @return ExternalIdentity
     */
    private function identity(string $subject, string $accessToken): ExternalIdentity {
        $response = Http::withToken($accessToken)->get(self::USERINFO_URL);
        if (!$response->successful()) throw new RuntimeException('Yahoo! JAPAN の UserInfo を取れませんでした: ' . $response->status());

        $info = $response->json();
        if (!is_array($info) || ($info['sub'] ?? null) !== $subject) throw new RuntimeException('UserInfo の sub が id_token と一致しません');

        $email = $info['email'] ?? null;
        $name = $info['name'] ?? $info['nickname'] ?? null;

        return new ExternalIdentity(
            self::NAME,
            $subject,
            is_string($email) && $email !== '' ? $email : null,
            ($info['email_verified'] ?? false) === true,
            is_string($name) && $name !== '' ? $name : null,
        );
    }

    /**
     * @return string
     */
    private function clientId(): string {
        return (string) config('yahoo-japan.client_id', '');
    }

    /**
     * @return string
     */
    private function clientSecret(): string {
        return (string) config('yahoo-japan.client_secret', '');
    }

    /**
     * 本体の戻り先。Yahoo! JAPAN 側のアプリ設定にも同じ URL を登録する。
     *
     * @return string
     */
    private function redirectUri(): string {
        return rtrim((string) config('chreeid.issuer'), '/') . '/auth/' . self::NAME . '/callback';
    }
}
