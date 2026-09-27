<?php
namespace App\Modules\Provider\Application;

/**
 * プラグインへ渡す、サインインの結果。
 *
 * sub と属性は OIDC の id_token、userinfo と同じ値。同じサービスアカウントなら、
 * どの方式で入っても同じ人として突き合わせられる。
 */
readonly class SignedInService {
    public string $clientId;
    public string $subject;
    /** @var array<string, mixed> */
    public array $claims;

    /**
     * @param string $clientId
     * @param string $subject サービスに渡す sub
     * @param array<string, mixed> $claims スコープの範囲の属性 (email、name など)
     */
    public function __construct(string $clientId, string $subject, array $claims) {
        $this->clientId = $clientId;
        $this->subject = $subject;
        $this->claims = $claims;
    }
}
