<?php
namespace Plugins\WikiHub;

use App\Modules\Plugin\Application\PluginApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 連携している各サービスから、利用者のウィキを集める。
 */
class ListWikis {
    private readonly PluginApi $api;
    private readonly WikiSourceClient $client;
    /** @var list<WikiSource> */
    private readonly array $sources;
    private readonly int $cacheSeconds;

    /**
     * @param PluginApi $api
     * @param WikiSourceClient $client
     * @param list<WikiSource> $sources 問い合わせ先
     * @param int $cacheSeconds 取得結果を持っておく秒数
     */
    public function __construct(PluginApi $api, WikiSourceClient $client, array $sources, int $cacheSeconds) {
        $this->api = $api;
        $this->client = $client;
        $this->sources = $sources;
        $this->cacheSeconds = $cacheSeconds;
    }

    /**
     * 連携していないサービスは出さない。
     *
     * @param string $accountId アカウントID (ULID)
     * @return list<array{key: string, label: string, failed: bool, wikis: list<array<string, mixed>>}>
     */
    public function execute(string $accountId): array {
        $groups = [];

        foreach ($this->sources as $source) {
            $accounts = $this->api->serviceAccounts($accountId, $source->clientId);
            if ($accounts === []) continue;

            $wikis = $this->fetchAll($source, $accountId, $accounts);
            $groups[] = ['key' => $source->key, 'label' => $source->label, 'failed' => $wikis === null, 'wikis' => $wikis ?? []];
        }

        return $groups;
    }

    /**
     * 1つのサービスが落ちても、他のサービスの分は出す。
     *
     * @param WikiSource $source 問い合わせ先
     * @param string $accountId アカウントID (ULID)
     * @param list<array{sub: string|null, serviceUserId: string|null}> $accounts そのサービスでのサービスアカウント
     * @return list<array<string, mixed>>|null 取れなければ null
     */
    private function fetchAll(WikiSource $source, string $accountId, array $accounts): ?array {
        try {
            return Cache::remember(
                "wiki-hub:{$source->key}:{$accountId}",
                $this->cacheSeconds,
                fn (): array => array_merge(...array_map(
                    fn (array $account): array => $this->client->fetch($source, $account['sub'], $account['serviceUserId']),
                    $accounts,
                )),
            );
        } catch (RuntimeException $e) {
            Log::warning($e->getMessage(), ['exception' => $e->getPrevious() ?? $e]);

            return null;
        }
    }
}
