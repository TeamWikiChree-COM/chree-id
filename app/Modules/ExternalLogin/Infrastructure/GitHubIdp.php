<?php
namespace App\Modules\ExternalLogin\Infrastructure;

use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\ExternalLogin\Domain\ExternalIdp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * GitHub との連携 (ChreeID が RP 側)
 *
 * **GitHub は OIDC ではなく素の OAuth2。** id_token が無いので、
 * アクセストークンで API を叩いて本人の情報を取りに行く。
 * そのため nonce は使わない (載せる先が無い)。state は呼び出し側が見ている。
 *
 * scope はログインに必要な最小限に留める。`repo` のような強い権限は求めない。
 */
class GitHubIdp implements ExternalIdp {
    private const AUTHORIZE_URL = 'https://github.com/login/oauth/authorize';
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const USER_URL = 'https://api.github.com/user';
    private const EMAILS_URL = 'https://api.github.com/user/emails';

    /** 本人とメールアドレスを読むだけ。これ以上は要らない */
    private const SCOPE = 'read:user user:email';

    /** GitHub の API は User-Agent を必須にしている */
    private const USER_AGENT = 'ChreeID';

    /**
     * @return string
     */
    public function name(): string {
        return 'github';
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * @param string $state CSRF 対策の値
     * @param string $nonce GitHub では使わない (id_token が無いため)
     * @return string
     */
    public function authorizationUrl(string $state, string $nonce): string {
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPE,
            'state' => $state,
        ]);
    }

    /**
     * @param string $code GitHub が返した認可コード
     * @param string $nonce GitHub では使わない
     * @return ExternalIdentity
     * @throws RuntimeException 交換や取得に失敗した場合
     */
    public function exchange(string $code, string $nonce): ExternalIdentity {
        $token = $this->accessToken($code);
        $user = $this->fetchUser($token);

        $subject = $user['id'] ?? null;
        // GitHub の id は数値で返る。identifier は文字列で持つ
        if (!is_int($subject) && !is_string($subject)) throw new RuntimeException('GitHub の id を取得できませんでした');

        $subject = (string) $subject;
        if ($subject === '') throw new RuntimeException('GitHub の id を取得できませんでした');

        $login = $user['login'] ?? null;
        $name = $user['name'] ?? null;

        [$email, $verified] = $this->primaryEmail($token, $user);

        return new ExternalIdentity(
            $this->name(),
            $subject,
            $email,
            $verified,
            is_string($name) && $name !== '' ? $name : (is_string($login) ? $login : null),
        );
    }

    /**
     * 認可コードをアクセストークンに交換する。
     *
     * **GitHub は失敗しても 200 を返す。** 本文の error を見ないと通ってしまう。
     *
     * @param string $code 認可コード
     * @return string アクセストークン
     * @throws RuntimeException 交換に失敗した場合
     */
    private function accessToken(string $code): string {
        // 既定では form 形式で返ってくるので、JSON を明示して受け取る
        $response = Http::asForm()->acceptJson()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('GitHub とのトークン交換に失敗しました');
        }

        $error = $response->json('error');
        if (is_string($error) && $error !== '') {
            throw new RuntimeException("GitHub がトークン交換を断りました: {$error}");
        }

        $token = $response->json('access_token');
        if (!is_string($token) || $token === '') throw new RuntimeException('アクセストークンが返りませんでした');

        return $token;
    }

    /**
     * @param string $token アクセストークン
     * @return array<mixed> /user の内容
     * @throws RuntimeException 取得に失敗した場合
     */
    private function fetchUser(string $token): array {
        $response = $this->api($token)->get(self::USER_URL);

        if (!$response->successful()) throw new RuntimeException('GitHub のユーザー情報を取得できませんでした');

        $user = $response->json();

        return is_array($user) ? $user : throw new RuntimeException('GitHub のユーザー情報を読めません');
    }

    /**
     * 連絡先に使うアドレスを決める。
     *
     * **`/user` の email は当てにしない。** 公開プロフィールに載せている値で、
     * 検証済みとは限らない。検証済みでないアドレスを検証済みとして渡すと、
     * 被害者のアドレスで作った GitHub アカウントから既存の ChreeID を乗っ取れる
     * (LinkExternalIdentity がそこを見て紐付ける)。
     *
     * 非公開にしている人は `/user/emails` も読めないことがあるので、
     * 取れなければ「アドレス無し」で通す。ログインまで止める必要はない。
     *
     * @param string $token アクセストークン
     * @param array<mixed> $user /user の内容
     * @return array{0: string|null, 1: bool} アドレスと、検証済みか
     */
    private function primaryEmail(string $token, array $user): array {
        $response = $this->api($token)->get(self::EMAILS_URL);

        if ($response->successful()) {
            $rows = $response->json();

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    if (($row['primary'] ?? false) !== true) continue;
                    if (($row['verified'] ?? false) !== true) continue;

                    $email = $row['email'] ?? null;
                    if (is_string($email) && $email !== '') return [$email, true];
                }
            }
        }

        // 検証済みと言い切れないので、紐付けの根拠には使わせない
        $fallback = $user['email'] ?? null;

        return [is_string($fallback) && $fallback !== '' ? $fallback : null, false];
    }

    /**
     * @param string $token アクセストークン
     * @return PendingRequest User-Agent を付けた API 用のクライアント
     */
    private function api(string $token): PendingRequest {
        return Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => self::USER_AGENT,
            ]);
    }

    /** @return string */
    private function clientId(): string {
        $value = config('services.github.client_id');

        return is_string($value) ? $value : '';
    }

    /** @return string */
    private function clientSecret(): string {
        $value = config('services.github.client_secret');

        return is_string($value) ? $value : '';
    }

    /** @return string */
    private function redirectUri(): string {
        $issuer = config('chreeid.issuer');

        return (is_string($issuer) ? rtrim($issuer, '/') : '') . '/auth/github/callback';
    }
}
