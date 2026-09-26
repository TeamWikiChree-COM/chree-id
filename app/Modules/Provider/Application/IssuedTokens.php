<?php
namespace App\Modules\Provider\Application;

/**
 * トークンエンドポイントで返すもの。
 */
final class IssuedTokens {
    /** 平文のアクセストークン。保存しているのはハッシュだけなので、返せるのはこの1回きり */
    public readonly string $accessToken;
    public readonly string $idToken;
    public readonly string $scope;
    /** アクセストークンの寿命 (秒) */
    public readonly int $expiresIn;

    /**
     * @param string $accessToken 平文のアクセストークン
     * @param string $idToken 署名済みの ID Token
     * @param string $scope 許可した scope (空白区切り)
     * @param int $expiresIn アクセストークンの寿命 (秒)
     */
    public function __construct(string $accessToken, string $idToken, string $scope, int $expiresIn) {
        $this->accessToken = $accessToken;
        $this->idToken = $idToken;
        $this->scope = $scope;
        $this->expiresIn = $expiresIn;
    }
}
