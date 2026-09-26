<?php
namespace Plugins\WikiHub;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * サービスへ「この人のウィキ一覧」を問い合わせる。
 *
 * 取り決めは plugins/wiki-hub/README.md。
 */
class WikiSourceClient {
    private readonly int $timeout;

    /**
     * @param int $timeout 待ち秒数
     */
    public function __construct(int $timeout) {
        $this->timeout = $timeout;
    }

    /**
     * @param WikiSource $source 問い合わせ先
     * @param string|null $sub OIDC で渡した sub
     * @param string|null $serviceUserId サービス側の識別子
     * @return list<array{name: string, url: string, iconUrl: string|null, settingsUrl: string|null, views: int|null, updatedAt: string|null}>
     * @throws RuntimeException 通信に失敗した・形が違う場合
     */
    public function fetch(WikiSource $source, ?string $sub, ?string $serviceUserId): array {
        try {
            // Authorization は CGI 方式の PHP で落とされることがあるので、同じ鍵を独自ヘッダーでも送る
            $response = Http::withToken($source->token)
                ->withHeaders(['X-Wiki-Hub-Token' => $source->token])
                ->timeout($this->timeout)
                ->acceptJson()
                ->get($source->endpoint, array_filter(['sub' => $sub, 'service_user_id' => $serviceUserId]));
        } catch (Throwable $e) {
            throw new RuntimeException("wiki-hub: {$source->key} へ繋がりません", 0, $e);
        }

        // その利用者がサービス側にまだいないだけ。失敗ではない。
        // ただし JSON でない 404 は入口そのものが無い (置き忘れ・URL 違い) ので失敗として出す
        if ($response->status() === 404 && is_array($response->json())) return [];
        if (!$response->successful()) throw new RuntimeException("wiki-hub: {$source->key} が {$response->status()} を返しました");

        $wikis = $response->json('wikis');
        if (!is_array($wikis)) throw new RuntimeException("wiki-hub: {$source->key} の応答に wikis がありません");

        return array_values(array_filter(array_map($this->normalize(...), $wikis)));
    }

    /**
     * @param mixed $row 応答の1件
     * @return array{name: string, url: string, iconUrl: string|null, settingsUrl: string|null, views: int|null, updatedAt: string|null}|null 使えない行は null
     */
    private function normalize(mixed $row): ?array {
        if (!is_array($row) || !is_string($row['name'] ?? null) || !is_string($row['url'] ?? null)) return null;

        return [
            'name' => $row['name'],
            'url' => $row['url'],
            'iconUrl' => is_string($row['icon_url'] ?? null) ? $row['icon_url'] : null,
            'settingsUrl' => is_string($row['settings_url'] ?? null) ? $row['settings_url'] : null,
            'views' => is_int($row['views'] ?? null) ? $row['views'] : null,
            'updatedAt' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }
}
