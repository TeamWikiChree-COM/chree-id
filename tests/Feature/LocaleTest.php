<?php

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Support\Locale\LocaleNegotiator;
use Illuminate\Http\Request;

/**
 * 表示言語の決め方。
 *
 * **既定はブラウザの Accept-Language。** 選ばないままなら端末の設定に追従する。
 * 本人が選んだときだけ、それが上に来る。
 */
class LocaleTest extends \Tests\TestCase {
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * @param array<string, string> $headers 送るヘッダ
     * @return string 決まった言語
     */
    private function localeFor(array $headers = [], ?string $cookie = null): string {
        $cookies = $cookie === null ? [] : [LocaleNegotiator::COOKIE => $cookie];

        $this->withHeaders($headers)->withCookies($cookies)->get('/login')->assertOk();

        return app()->getLocale();
    }

    public function test_followsTheBrowserWhenNothingIsChosen(): void {
        $this->assertSame('en', $this->localeFor(['Accept-Language' => 'en-US,en;q=0.9']));
        $this->assertSame('ja', $this->localeFor(['Accept-Language' => 'ja,en;q=0.8']));
    }

    // 地域まで分けた辞書は持っていないので、ja-JP は ja として扱う
    public function test_ignoresTheRegionPart(): void {
        $this->assertSame('ja', $this->localeFor(['Accept-Language' => 'ja-JP']));
    }

    // 重みが大きいほうを採る。並び順ではなく q を見ていることの確認
    public function test_respectsTheQualityValue(): void {
        $this->assertSame('ja', $this->localeFor(['Accept-Language' => 'en;q=0.2,ja;q=0.9']));
    }

    public function test_fallsBackWhenTheBrowserAsksForSomethingWeDoNotHave(): void {
        $this->assertSame('ja', $this->localeFor(['Accept-Language' => 'fr-FR,fr;q=0.9']));
    }

    // ヘッダが無い相手 (curl など) も来る。
    // **HTTP 経由では確かめられない** — Symfony のテスト用 Request は
    // 既定で Accept-Language: en-us を付けるので、直接呼んで確かめる
    public function test_fallsBackWhenThereIsNoHeader(): void {
        $request = Request::create('/login');
        $request->headers->remove('Accept-Language');

        $locale = app(LocaleNegotiator::class)->resolve($request);

        $this->assertSame('ja', $locale);
    }

    // 選んだものはブラウザの設定より強い
    public function test_prefersTheChoiceOverTheBrowser(): void {
        $this->assertSame('en', $this->localeFor(['Accept-Language' => 'ja'], 'en'));
    }

    // Cookie は手で書き換えられる。知らない名前ならブラウザの設定に戻す
    public function test_ignoresAnUnknownCookie(): void {
        $this->assertSame('en', $this->localeFor(['Accept-Language' => 'en'], 'xx'));
    }

    public function test_remembersTheChoiceInACookie(): void {
        $this->post('/locale', ['locale' => 'en'])
            ->assertRedirect()
            ->assertCookie(LocaleNegotiator::COOKIE, 'en');
    }

    public function test_refusesAnUnknownLocale(): void {
        $this->post('/locale', ['locale' => 'xx'])
            ->assertRedirect()
            ->assertCookieMissing(LocaleNegotiator::COOKIE);
    }

    // ログイン中は行に持たせる。端末を変えても付いてくるようにするため
    public function test_storesTheChoiceOnTheAccountWhenSignedIn(): void {
        $account = app(AuthIdentityRepository::class)->create(AccountOrigin::USER, 'user@example.com', '本人');

        $this->withSession(['chreeid.account_id' => $account->id])
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('en', AuthIdentityModel::query()->findOrFail($account->id)->locale);
    }
}
