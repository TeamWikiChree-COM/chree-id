<?php
namespace Tests\Unit;

use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Domain\PluginMenuItem;
use PHPUnit\Framework\TestCase;

// ダッシュボードには、連携しているサービスで使えるプラグインだけを出す
class PluginMenuTest extends TestCase {
    public function test_showsOnlyToConnectedUsers(): void {
        $menu = new PluginMenu();
        $menu->add(new PluginMenuItem(PluginMenu::AREA_DASHBOARD, '/plugins/a', ['ja' => 'A'], [], ['client-1']));

        $this->assertSame([], $menu->itemsFor(PluginMenu::AREA_DASHBOARD, 'ja', ['client-2']));
        $this->assertCount(1, $menu->itemsFor(PluginMenu::AREA_DASHBOARD, 'ja', ['client-1']));
    }

    public function test_withoutClientsShowsToEveryone(): void {
        $menu = new PluginMenu();
        $menu->add(new PluginMenuItem(PluginMenu::AREA_DASHBOARD, '/plugins/a', ['ja' => 'A', 'en' => 'B']));

        $this->assertSame('B', $menu->itemsFor(PluginMenu::AREA_DASHBOARD, 'en')[0]['label']);
        $this->assertSame([], $menu->itemsFor(PluginMenu::AREA_ADMIN, 'en'));
    }
}
