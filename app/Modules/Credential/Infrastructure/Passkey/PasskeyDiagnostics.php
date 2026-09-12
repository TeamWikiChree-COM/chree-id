<?php
namespace App\Modules\Credential\Infrastructure\Passkey;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * パスキー登録が失敗したときの切り分け材料。
 *
 * **ここは原因を直さない。次に再現したときに原因が分かるようにするためだけに居る。**
 * 「登録できませんでした (401)」だけでは、クッキーが届いていないのか、セッションが
 * 消えたのか、別のホストで開いているのかが区別できない。
 */
class PasskeyDiagnostics {
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
     * 「クッキーが届いたか」と「その中身が既知のセッションか」が分かれば足りる。
     *
     * @param Request $request 来ている要求
     * @return array<string, bool|string>
     */
    private function facts(Request $request): array {
        $cookie = $request->cookies->get(config()->string('session.cookie'));

        return [
            // 届いていなければブラウザ側 (クッキーが落ちている)。届いていればサーバ側
            'session_cookie_sent' => $cookie !== null,
            'session_started' => $request->hasSession() && $request->session()->isStarted(),
            'host' => (string) $request->getHost(),
            'rp_id' => $this->context->rpId(),
            // ずれているとブラウザが必ず断る
            'rp_matches_host' => $this->context->matchesHost($request->getHost()),
            // WebAuthn は secure context が要る。localhost だけは例外
            'secure' => $request->isSecure(),
        ];
    }
}
