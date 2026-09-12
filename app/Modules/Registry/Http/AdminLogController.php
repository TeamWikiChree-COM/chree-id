<?php
namespace App\Modules\Registry\Http;

use App\Modules\Registry\Infrastructure\LogFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * アプリケーションログの閲覧。
 *
 * 本番は共用サーバでシェルに入れないので、ここから読めないと
 * 例外の中身を確かめる手段が無い。
 *
 * **監査ログ (AdminAuditController) とは別物。** あちらは「誰に何が起きたか」の
 * 記録で、こちらは「どこで壊れたか」の記録。混ぜると読む目的が濁る。
 */
class AdminLogController {
    private readonly LogFile $logs;

    public function __construct(LogFile $logs) {
        $this->logs = $logs;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response {
        $files = $this->logs->available();

        $chosen = $request->string('file')->toString();
        if (!in_array($chosen, $files, true)) $chosen = $files[0] ?? '';

        return Inertia::render('Admin/Logs/Index', [
            'files' => $files,
            'file' => $chosen,
            'entries' => $chosen === '' ? [] : $this->logs->tail($chosen),
        ]);
    }

    /**
     * 中身を空にする。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function clear(Request $request): RedirectResponse {
        $request->validate(['file' => ['required', 'string']]);

        $this->logs->clear($request->string('file')->toString());

        return redirect('/admin/logs')->with('logCleared', true);
    }
}
