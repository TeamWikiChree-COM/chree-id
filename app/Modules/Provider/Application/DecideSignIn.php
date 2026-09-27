<?php
namespace App\Modules\Provider\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Provider\Domain\SignInStepKind;

/**
 * サービスへのサインインで、次に何をするかを決める。
 *
 * OIDC の /oauth/authorize も、プラグイン (SAML など) から頼まれたサインインもここを通る。
 * 方式ごとに書くと、停止や引き取り前の確認を片方だけ直す取りこぼしが起きる。
 * 画面やリダイレクトは呼び出し側が組む。ここは判断だけ。
 */
class DecideSignIn {
    private readonly UnclaimedServiceAccountGuard $unclaimed;
    private readonly SelectServiceAccount $select;

    public function __construct(UnclaimedServiceAccountGuard $unclaimed, SelectServiceAccount $select) {
        $this->unclaimed = $unclaimed;
        $this->select = $select;
    }

    /**
     * @param OAuthClientModel $client 接続先サービス。信頼状態は呼び出し側で確かめてあること
     * @param string|null $accountId ログイン中の認証主体。未ログインなら null
     * @param bool $consented 同意画面で許可された後か
     * @param string|null $chosenServiceAccountId 画面で選ばれたサービスアカウント
     * @return SignInStep
     */
    public function execute(OAuthClientModel $client, ?string $accountId, bool $consented, ?string $chosenServiceAccountId): SignInStep {
        if ($accountId === null) return SignInStep::of(SignInStepKind::LOGIN);
        if ($this->unclaimed->blocks($client, $accountId)) return SignInStep::of(SignInStepKind::UNCLAIMED);

        // 公式サービスは ChreeID の一部とみなせるので、毎回の同意を求めない
        // 同意の省略は信頼状態とは別の設定。承認済みでも省略したいサービスがある
        if (!$consented && !$client->skips_consent) return SignInStep::of(SignInStepKind::CONSENT);

        try {
            return SignInStep::granted($this->select->execute($client, $accountId, $chosenServiceAccountId));
        } catch (AmbiguousServiceAccountException) {
            // 統合で同じサービスに複数持っている人。黙ってどれかを選ぶと別人として入れてしまう
            return SignInStep::chooseAccount($this->select->candidates($client, $accountId));
        }
    }
}
