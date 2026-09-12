<?php
namespace App\Modules\Credential\Infrastructure\Passkey;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * パスキー登録が失敗したときの切り分け材料。
 *
 * **ここは原因を直さない。再現したときに原因が分かるようにするためだけに居る。**
 * 「登録できませんでした (401)」だけでは、クッキーが届いていないのか、セッションの
 * 中身が消えたのか、別のセッションに繋がっているのかが区別できない。
 *
 * 実際これで、options をセッションに入れると保存が壊れることを突き止めた
 * (PasskeyChallenges 参照)。同じ形の不具合はまた出るので、残してある。
 */
class PasskeyDiagnostics {
    /**
     * 診断の版。**必ず出力に載せる。**
     * 載せないと、古い版が動いているのか本当に情報が無いのかを、ログから区別できない。
     */
    private const VERSION = '2026-09-13';

    private readonly PasskeyContext $context;

    public function __construct(PasskeyContext $context) {
        $this->context = $context;
    }

    /**
     * 記録だけ残す。呼び出し側の応答は変えない。
     *
     * @param string $step どの往復で起きたか ('options' / 'register')
     * @param Request $request 来ている要求
     * @return void
     */
    public function reportMissingSession(string $step, Request $request): void {
        report(new RuntimeException(
            "passkey {$step}: セッションが見つからない " . json_encode($this->facts($request), JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * 切り分けに要る事実だけを集める。
     *
     * **セッションIDそのものは載せない。** ログを読める人がなりすませてしまう。
     *
     * @param Request $request 来ている要求
     * @return array<string, bool|int|string|null>
     */
    private function facts(Request $request): array {
        $cookie = $request->cookies->get(config()->string('session.cookie'));
        $session = $request->hasSession() ? $request->session() : null;

        return [
            'diag' => self::VERSION,
            // 往復のあいだで変われば、ブラウザが別のクッキーを送っている
            'session' => $this->fingerprint($session?->getId()),
            // 届いていなければブラウザ側 (クッキーが落ちている)。届いていればサーバ側
            'session_cookie_sent' => $cookie !== null,
            'session_started' => $session !== null && $session->isStarted(),
            'session_driver' => config()->string('session.driver'),
            // 1 (= _token だけ) なら、実質まっさらなセッション
            'session_keys' => $session === null ? -1 : count($session->all()),
            // false なら保管されている実体が消えている
            'session_row_exists' => $this->rowExists($session?->getId()),
            'host' => (string) $request->getHost(),
            'rp_id' => $this->context->rpId(),
            // ずれているとブラウザが必ず断る
            'rp_matches_host' => $this->context->matchesHost($request->getHost()),
            // WebAuthn は secure context が要る。localhost だけは例外
            'secure' => $request->isSecure(),
        ];
    }

    /**
     * セッションIDの指紋。
     *
     * **IDそのものは載せない** (ログを読める人がなりすませる)。
     * 同じかどうかが分かれば足りるので、ハッシュの先頭だけにする。
     *
     * @param string|null $id セッションID
     * @return string|null
     */
    private function fingerprint(?string $id): ?string {
        return $id === null ? null : substr(hash('sha256', $id), 0, 8);
    }

    /**
     * 保管されているセッションの実体が残っているか。
     *
     * database ドライバのときだけ調べられる。他のドライバでは分からないので null。
     *
     * @param string|null $id セッションID
     * @return bool|null
     */
    private function rowExists(?string $id): ?bool {
        if ($id === null || config()->string('session.driver') !== 'database') return null;

        return DB::table(config()->string('session.table'))->where('id', $id)->exists();
    }
}
