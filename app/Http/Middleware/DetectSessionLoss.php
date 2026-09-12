<?php
namespace App\Http\Middleware;

use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Support\Session\SignedInMarker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 勝手にログアウトされた瞬間を記録する。
 *
 * **症状の出た画面ではなく、失われた要求そのものを捕まえる。** パスキー登録が
 * 401 になるのは結果であって、セッションから利用者が消えたのはその前。
 * どの要求で消えたのかが分からないと、原因に手が届かない。
 *
 * 意図したログアウトでは印を外しているので (ChreeSession::logout)、
 * ここに引っかかるのは意図しないものだけになる。
 */
class DetectSessionLoss {
    private readonly ChreeSession $session;
    private readonly SignedInMarker $marker;

    public function __construct(ChreeSession $session, SignedInMarker $marker) {
        $this->session = $session;
        $this->marker = $marker;
    }

    /**
     * @param Request $request 来ている要求
     * @param Closure(Request): Response $next 次の処理
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        if ($this->session->isLoggedIn()) {
            $this->marker->mark($request);

            return $next($request);
        }

        // 一度もログインしていないブラウザなら、居ないのが普通
        if (!$this->marker->isMarked($request)) return $next($request);

        Log::warning('session lost: ' . json_encode($this->facts($request), JSON_THROW_ON_ERROR));

        // 出すのは1回だけ。以降の要求すべてで鳴らしても何も分からない
        $this->marker->forget();

        return $next($request);
    }

    /**
     * 何が残っていて何が消えたのかを集める。
     *
     * **セッションIDそのものは載せない。** ログを読める人がなりすませてしまう。
     *
     * @param Request $request 来ている要求
     * @return array<string, bool|int|string|null>
     */
    private function facts(Request $request): array {
        $session = $request->session();
        $keys = array_keys($session->all());

        return [
            // どの要求で消えたか。ここが犯人のいる場所
            'path' => $request->path(),
            'method' => $request->method(),
            // 0 なら中身ごと失われている。1以上なら「利用者だけ」が消えた
            'session_keys' => count($keys),
            // _token が残っていれば、セッション自体は同じものが続いている
            'has_token' => in_array('_token', $keys, true),
            'has_pending_auth' => $session->has('chreeid.pending_auth'),
            'session_driver' => config()->string('session.driver'),
            // false なら保管されている実体が消えている
            'session_row_exists' => $this->rowExists($session->getId()),
            'referer' => (string) $request->headers->get('referer'),
        ];
    }

    /**
     * @param string $id セッションID
     * @return bool|null database ドライバでなければ分からないので null
     */
    private function rowExists(string $id): ?bool {
        if (config()->string('session.driver') !== 'database') return null;

        return DB::table(config()->string('session.table'))->where('id', $id)->exists();
    }
}
