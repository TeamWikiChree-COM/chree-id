<?php
namespace App\Modules\Provider\Application;

/**
 * 済んだサインインの中身。プラグインへは引換券で渡し、受け取ったときに属性へ解決する。
 */
readonly class SignInGrant {
    public string $clientId;
    public string $serviceAccountId;
    /** @var list<string> */
    public array $scopes;

    /**
     * @param string $clientId
     * @param string $serviceAccountId どのサービスアカウントとして入るか
     * @param list<string> $scopes
     */
    public function __construct(string $clientId, string $serviceAccountId, array $scopes) {
        $this->clientId = $clientId;
        $this->serviceAccountId = $serviceAccountId;
        $this->scopes = $scopes;
    }
}
