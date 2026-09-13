<?php
namespace Tests\Feature;

use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Tests\TestCase;

// API ドキュメントが実装とずれていないか。仕様だけ古いまま、を落とす
class OpenApiSpecTest extends TestCase {
    use RefreshDatabase;

    /** 仕様に載せない経路。仕様そのものは載せても意味が無い */
    private const UNDOCUMENTED = ['api/v1/openapi.json'];

    /** ブラウザを介さない OIDC の経路。/oauth/authorize は画面なので対象外 */
    private const OIDC = ['.well-known/openid-configuration', 'oauth/jwks', 'oauth/token', 'oauth/userinfo'];

    /**
     * @return array<string, mixed>
     */
    private function spec(): array {
        $response = $this->getJson('/api/v1/openapi.json')->assertOk();

        /** @var array<string, mixed> $spec */
        $spec = $response->json();

        return $spec;
    }

    /**
     * @return array<string, array<mixed>> パス => メソッド => 操作
     */
    private function paths(): array {
        $paths = $this->spec()['paths'] ?? null;
        if (!is_array($paths)) $this->fail('paths がありません');

        $result = [];
        foreach ($paths as $path => $operations) {
            if (!is_array($operations)) $this->fail("{$path} の操作が配列ではありません");

            $result[(string) $path] = $operations;
        }

        return $result;
    }

    public function test_servesTheSpecWithoutAuthentication(): void {
        $spec = $this->spec();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertNotEmpty($spec['paths']);
    }

    // 実装に足した経路を仕様に書き忘れると、ここで落ちる
    public function test_documentsEveryServerToServerRoute(): void {
        $paths = $this->paths();

        foreach ($this->serverRoutes() as $route) {
            $path = '/' . $route->uri();
            $this->assertArrayHasKey($path, $paths, "{$path} が仕様にありません");

            foreach ($route->methods() as $method) {
                if (!is_string($method) || $method === 'HEAD') continue;

                $this->assertArrayHasKey(strtolower($method), $paths[$path], "{$method} {$path} が仕様にありません");
            }
        }
    }

    // 逆に、消した経路が仕様に残っていないこと
    public function test_doesNotDocumentMissingRoutes(): void {
        $uris = array_map(fn (Route $route): string => '/' . $route->uri(), $this->serverRoutes());

        foreach (array_keys($this->paths()) as $path) {
            $this->assertContains($path, $uris, "{$path} は実装にありません");
        }
    }

    // 翻訳キーのまま出ていたら、キーの綴り違いか lang の足し忘れ
    public function test_hasNoUntranslatedText(): void {
        $json = json_encode($this->spec(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->assertDoesNotMatchRegularExpression('/"apidocs\.[a-z_.]+"/', $json);
    }

    public function test_showsTheDocsPage(): void {
        $this->get('/api-docs/v1')->assertOk()->assertSee('/api/v1/openapi.json');
    }

    public function test_refusesAnUnknownVersion(): void {
        $this->get('/api-docs/v9')->assertNotFound();
    }

    // 入力エラーも他の失敗と同じ形で返す。読み口が2つになると呼び出し元が困る
    public function test_validationErrorsUseTheApiErrorShape(): void {
        $client = OAuthClientModel::create([
            'id' => Str::lower(Str::ulid()->toString()),
            'secret_hash' => hash('sha256', 'service-secret'),
            'name' => 'DokuFarm',
            'redirect_uris' => ['https://doku.example.com/auth/chreeid/callback'],
            'scopes' => 'openid profile email',
            'is_confidential' => true,
            'trust' => ServiceTrust::OFFICIAL,
            'can_provision' => true,
        ]);

        $this->postJson('/api/v1/service-accounts/status', [
            'client_id' => $client->id,
            'client_secret' => 'service-secret',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_request')
            ->assertJsonValidationErrors('service_user_id');
    }

    // サーバ間 API にセッションを張らない。張るとリクエストのたびにセッションが増える
    public function test_apiRoutesDoNotStartASession(): void {
        foreach ($this->serverRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')) continue;

            $this->assertNotContains('web', $route->gatherMiddleware(), $route->uri());
        }
    }

    /**
     * @return list<Route>
     */
    private function serverRoutes(): array {
        $routes = array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route): bool => !in_array($route->uri(), self::UNDOCUMENTED, true)
                && (str_starts_with($route->uri(), 'api/') || in_array($route->uri(), self::OIDC, true)),
        );

        return array_values($routes);
    }
}
