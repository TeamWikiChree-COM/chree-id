<?php
namespace Tests\Unit;

use App\Modules\Plugin\Domain\PluginManifest;
use App\Modules\Plugin\Domain\PluginMenu;
use App\Modules\Plugin\Domain\PluginMenuItem;
use LogicException;
use PHPUnit\Framework\Attributes\TestDox;
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

    #[TestDox('addPlugin は plugin.json の名前と説明を使い、/plugins/<名前> への入口を足す')]
    public function test_addPluginFillsFromManifest(): void {
        $menu = new PluginMenu();
        $menu->registerPlugin(new PluginManifest('example', '0.1.0', 'Provider', true, ['ja' => '例'], ['ja' => '説明']));
        $menu->addPlugin('example', PluginMenu::AREA_DASHBOARD);

        $this->assertSame([['href' => '/plugins/example', 'label' => '例', 'description' => '説明']], $menu->itemsFor(PluginMenu::AREA_DASHBOARD, 'ja'));
    }

    #[TestDox('読み込まれていないプラグインの名前を addPlugin に渡すと例外になる')]
    public function test_addPluginRejectsUnknownName(): void {
        $this->expectException(LogicException::class);

        (new PluginMenu())->addPlugin('exmaple', PluginMenu::AREA_DASHBOARD);
    }
}
