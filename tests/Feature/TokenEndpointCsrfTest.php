<?php
namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * トークンエンドポイントが CSRF の対象外になっているか。
 *
 * RP はブラウザのセッションを持たずにここへ POST するので、CSRF を通すと交換できない。
 * 実際にこれが漏れていて、RP からのトークン交換が 419 で全滅していた。
 *
 * 通常の Feature テストでは捕まらない。ValidateCsrfToken は
 * runningUnitTests() の間そもそも検証しないため、除外の有無が結果に出ない。
 * そこで除外リストそのものを確かめる。
 */
class TokenEndpointCsrfTest extends TestCase {
    /**
     * 除外リスト (protected) を読む。
     *
     * @return list<string>
     */
    private function exceptions(): array {
        $middleware = $this->app->make(ValidateCsrfToken::class);

        $method = new \ReflectionMethod($middleware, 'getExcludedPaths');

        /** @var list<string> $paths */
        $paths = $method->invoke($middleware);

        return $paths;
    }

    public function test_tokenEndpointIsExcludedFromCsrf(): void {
        $this->assertContains('oauth/token', $this->exceptions());
    }

    // ブラウザから来る画面まで外してしまっていないこと
    public function test_browserRoutesAreStillProtected(): void {
        $paths = $this->exceptions();

        foreach (['login', 'register', 'oauth/authorize/approve', '*'] as $path) {
            $this->assertNotContains($path, $paths);
        }
    }
}
