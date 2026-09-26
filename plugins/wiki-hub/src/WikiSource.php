<?php
namespace Plugins\WikiHub;

/**
 * ウィキ一覧を問い合わせるサービス1つ。
 */
final class WikiSource {
    public readonly string $key;
    public readonly string $label;
    public readonly string $clientId;
    public readonly string $endpoint;
    public readonly string $token;

    /**
     * @param string $key 設定上の名前 (dokufarm など)
     * @param string $label 画面に出すサービス名
     * @param string $clientId ChreeID に登録されている client_id
     * @param string $endpoint 一覧を返す URL
     * @param string $token 共有の鍵
     */
    public function __construct(string $key, string $label, string $clientId, string $endpoint, string $token) {
        $this->key = $key;
        $this->label = $label;
        $this->clientId = $clientId;
        $this->endpoint = $endpoint;
        $this->token = $token;
    }

    /**
     * 3つ揃っていない設定は、まだ繋いでいないサービスとして飛ばす。
     *
     * @param array<string, array<string, mixed>> $config config('wiki-hub.sources')
     * @return list<self>
     */
    public static function fromConfig(array $config): array {
        $sources = [];

        foreach ($config as $key => $row) {
            $clientId = $row['client_id'] ?? null;
            $endpoint = $row['endpoint'] ?? null;
            $token = $row['token'] ?? null;
            if (!is_string($clientId) || !is_string($endpoint) || !is_string($token)) continue;
            if ($clientId === '' || $endpoint === '' || $token === '') continue;

            $sources[] = new self($key, (string) ($row['label'] ?? $key), $clientId, $endpoint, $token);
        }

        return $sources;
    }
}
