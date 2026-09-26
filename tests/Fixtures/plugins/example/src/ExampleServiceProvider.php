<?php
namespace Plugins\Example;

use App\Modules\Plugin\Domain\PluginMenu;
use Illuminate\Support\ServiceProvider;

/**
 * 本体のプラグイン読み込みを確かめるためのプラグイン。
 */
class ExampleServiceProvider extends ServiceProvider {
    /**
     * @param PluginMenu $menu
     */
    public function boot(PluginMenu $menu): void {
        $menu->addPlugin('example', PluginMenu::AREA_DASHBOARD);
    }
}