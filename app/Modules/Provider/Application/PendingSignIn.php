<?php
namespace App\Modules\Provider\Application;

/**
 * プラグインから頼まれて、途中まで進めているサービスへのサインイン。
 */
readonly class PendingSignIn {
    public string $id;
    public string $clientId;
    /** @var list<string> */
    public array $scopes;
    public string $returnUrl;

    /**
     * @param string $id 画面と行き来する識別子
     * @param string $clientId 接続先サービス
     * @param list<string> $scopes 渡す属性の範囲
     * @param string $returnUrl 済んだら戻る先 (プラグインのルート)
     */
    public function __construct(string $id, string $clientId, array $scopes, string $returnUrl) {
        $this->id = $id;
        $this->clientId = $clientId;
        $this->scopes = $scopes;
        $this->returnUrl = $returnUrl;
    }
}
