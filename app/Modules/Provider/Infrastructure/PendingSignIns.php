<?php
namespace App\Modules\Provider\Infrastructure;

use App\Modules\Provider\Application\PendingSignIn;
use App\Modules\Provider\Application\SignInGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * プラグインから頼まれたサインインの、セッション上の持ち物。
 *
 * 途中の状態 (預かり) と、済んだ結果 (引換券) を置く。どちらもセッションに置くので、
 * 別のブラウザで開かれても続きは進まず、引換券も使えない。
 */
class PendingSignIns {
    private const PENDING = 'provider.pending_sign_in.';
    private const GRANT = 'provider.sign_in_grant.';

    private readonly Request $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    /**
     * @param string $clientId
     * @param list<string> $scopes
     * @param string $returnUrl
     * @return PendingSignIn
     */
    public function start(string $clientId, array $scopes, string $returnUrl): PendingSignIn {
        $pending = new PendingSignIn(Str::random(40), $clientId, $scopes, $returnUrl);
        $this->request->session()->put(self::PENDING . $pending->id, [
            'clientId' => $clientId,
            'scopes' => $scopes,
            'returnUrl' => $returnUrl,
        ]);

        return $pending;
    }

    /**
     * @param string $id
     * @return PendingSignIn|null 無い・壊れていれば null
     */
    public function find(string $id): ?PendingSignIn {
        $stored = $this->request->session()->get(self::PENDING . $id);
        if (!is_array($stored)) return null;

        return new PendingSignIn($id, (string) $stored['clientId'], array_values($stored['scopes']), (string) $stored['returnUrl']);
    }

    /**
     * 預かりを閉じ、引換券を出す。
     *
     * @param PendingSignIn $pending
     * @param SignInGrant $grant
     * @return string 引換券
     */
    public function complete(PendingSignIn $pending, SignInGrant $grant): string {
        $token = Str::random(40);
        $session = $this->request->session();
        $session->forget(self::PENDING . $pending->id);
        $session->put(self::GRANT . $token, [
            'clientId' => $grant->clientId,
            'serviceAccountId' => $grant->serviceAccountId,
            'scopes' => $grant->scopes,
        ]);

        return $token;
    }

    /**
     * 引換券を使う。一度使えば消える。
     *
     * @param string $token
     * @return SignInGrant|null
     */
    public function takeGrant(string $token): ?SignInGrant {
        if ($token === '') return null;

        $stored = $this->request->session()->pull(self::GRANT . $token);
        if (!is_array($stored)) return null;

        return new SignInGrant((string) $stored['clientId'], (string) $stored['serviceAccountId'], array_values($stored['scopes']));
    }
}
