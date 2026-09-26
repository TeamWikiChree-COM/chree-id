<?php
namespace Plugins\Template;

use App\Http\Controllers\Controller;
use App\Modules\Plugin\Application\PluginApi;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * プラグインの画面。
 */
class TemplateController extends Controller {
    private readonly PluginApi $api;

    public function __construct(PluginApi $api) {
        $this->api = $api;
    }

    /**
     * @return Response|RedirectResponse
     */
    public function index(): Response|RedirectResponse {
        // 本体の情報は、互換性を保つ窓口の PluginApi から取る
        if ($this->api->accountId() === null) return LoginRedirect::guest();

        // 画面は <フォルダ名>::<Pages 以下のファイル名> で指定する
        return Inertia::render('template::Index', ['greeting' => config('template.greeting')]);
    }
}
