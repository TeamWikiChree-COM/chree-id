<?php
namespace Plugins\YahooJapan;

use App\Modules\ExternalLogin\Domain\CodeExchangeIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdpDisplay;
use App\Modules\ExternalLogin\Infrastructure\IdTokenClaims;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Yahoo! JAPAN ID でのログイン (YConnect v2、ChreeID が RP 側)。
 *
 * id_token にはメールが載らない。メールと名前は属性取得 API (UserInfo) から取るが、審査に通ったアプリでしか使えず、
 * 個人の登録では使えない。そのため既定では使わず、id_token の sub だけで入れる (config の userinfo で切り替える)。
 */
class YahooJapanIdp implements CodeExchangeIdp {
    public const NAME = 'yahoo-japan';

    private const AUTHORIZE_URL = 'https://auth.login.yahoo.co.jp/yconnect/v2/authorization';
    private const TOKEN_URL = 'https://auth.login.yahoo.co.jp/yconnect/v2/token';
    private const USERINFO_URL = 'https://userinfo.yahooapis.jp/yconnect/v2/attribute';
    private const ISSUER = 'https://auth.login.yahoo.co.jp/yconnect/v2';

    /** 送り出したときの PKCE の code_verifier。戻ってきたら交換に使う */
    private const CODE_VERIFIER = 'yahoo-japan.code_verifier';

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
        // シークレットは任意。クライアントサイドで登録したアプリには発行されず、PKCE だけで交換する
        return $this->clientId() !== '';
    }

    /**
     * @return ExternalIdpDisplay
     */
    public function display(): ExternalIdpDisplay {
        return ExternalIdpDisplay::brand('Yahoo! JAPAN ID', 'yahoo', ExternalIdpDisplay::FAMILY_SOLID)
            ->withSvgFile(__DIR__ . '/../resources/icon.svg');
    }

    /**
     * @param string $state
     * @param string $nonce
     * @return string
     */
    public function authorizationUrl(string $state, string $nonce): string {
        // PKCE は常に付ける。シークレットの無いアプリでは、これが認可コードを横取りされないための唯一の守り
        $verifier = Str::random(64);
        session()->put(self::CODE_VERIFIER, $verifier);

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            // 審査を通っていないアプリに profile や email を求めると断られる。UserInfo を使わないなら openid だけ
            'scope' => $this->usesUserinfo() ? 'openid profile email' : 'openid',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * @param string $code
     * @param string $nonce
     * @return ExternalIdentity
     * @throws RuntimeException 交換や検証に失敗した場合
     */
    public function exchange(string $code, string $nonce): ExternalIdentity {
        $verifier = session()->pull(self::CODE_VERIFIER);
        if (!is_string($verifier)) throw new RuntimeException('code_verifier がありません');

        $request = Http::asForm();
        // シークレットがあるアプリは Basic でクライアント認証する。無いアプリは client_id と PKCE だけ
        if ($this->clientSecret() !== '') $request = $request->withBasicAuth($this->clientId(), $this->clientSecret());

        $response = $request->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $verifier,
        ]);
        if (!$response->successful()) throw new RuntimeException('Yahoo! JAPAN とのトークン交換に失敗しました: ' . $response->status());

        $idToken = $response->json('id_token');
        $accessToken = $response->json('access_token');
        if (!is_string($idToken) || !is_string($accessToken)) throw new RuntimeException('id_token か access_token が返りませんでした');

        $claims = $this->idTokens->read($idToken, [self::ISSUER], $this->clientId(), $nonce);
        $subject = (string) $claims['sub'];

        // メールが無いので、同じメールの既存アカウントへは寄せられない。既存の人は設定の「連携」から追加する
        if (!$this->usesUserinfo()) return new ExternalIdentity(self::NAME, $subject, null, false, null);

        return $this->identity($subject, $accessToken);
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
     * @return bool 属性取得 API (UserInfo) でメールと名前を取るか。審査に通ったアプリだけ
     */
    private function usesUserinfo(): bool {
        return (bool) config('yahoo-japan.userinfo', false);
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
