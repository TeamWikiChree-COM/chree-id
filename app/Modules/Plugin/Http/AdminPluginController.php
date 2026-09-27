<?php
namespace App\Modules\Plugin\Http;

use App\Http\Controllers\Controller;
use App\Modules\Plugin\Domain\PluginManifest;
use App\Modules\Plugin\Infrastructure\PluginRegistry;
use App\Modules\Plugin\Infrastructure\PluginSwitch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * プラグインの一覧と、有効と無効の切り替え (運営向け)。
 */
class AdminPluginController extends Controller {
    private readonly PluginRegistry $plugins;
    private readonly PluginSwitch $switch;

    public function __construct(PluginRegistry $plugins, PluginSwitch $switch) {
        $this->plugins = $plugins;
        $this->switch = $switch;
    }

    /**
     * @return Response
     */
    public function index(): Response {
        $locale = app()->getLocale();

        return Inertia::render('Admin/Plugins/Index', [
            'plugins' => array_map(static fn (PluginManifest $plugin): array => [
                'name' => $plugin->name,
                'title' => $plugin->titleFor($locale),
                'description' => $plugin->description[$locale] ?? null,
                'version' => $plugin->version,
                'enabled' => $plugin->enabled,
            ], $this->plugins->all()),
        ]);
    }

    /**
     * @param Request $request
     * @param string $name プラグインのフォルダ名
     * @return RedirectResponse
     */
    public function update(Request $request, string $name): RedirectResponse {
        $plugin = $this->plugins->find($name);
        if ($plugin === null) abort(404);

        $this->switch->set($plugin, $request->boolean('enabled'));

        return redirect('/admin/plugins');
    }
}
