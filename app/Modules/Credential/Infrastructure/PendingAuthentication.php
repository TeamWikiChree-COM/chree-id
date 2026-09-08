<?php
namespace App\Modules\Credential\Infrastructure;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactor;
use App\Modules\Credential\Domain\VerifiedFactors;
use Illuminate\Http\Request;

/**
 * 一次認証は通ったが、まだ認証が成立していないログイン試行。
 *
 * 二要素目を入力してもらう間の置き場。ここにあるのは「まだログインしていない」状態で、
 * ChreeSession とは別に持つ。取り違えるとパスワードだけでログインできてしまう。
 */
class PendingAuthentication {
    private const KEY = 'chreeid.pending_auth';

    /** 二要素目を待つ時間(分)。長く置くほど、端末を離れた隙に完了される余地が増える */
    private const EXPIRES_MINUTES = 10;

    public function __construct(private readonly Request $request) {}

    /**
     * 一次認証の結果を預ける。
     *
     * @param string $accountId アカウントID (ULID)
     * @param VerifiedFactors $factors ここまでに検証できた要素
     * @return void
     */
    public function start(string $accountId, VerifiedFactors $factors): void {
        $this->request->session()->put(self::KEY, [
            'account_id' => $accountId,
            'factors' => $this->toArray($factors),
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES)->getTimestamp(),
        ]);
    }

    /**
     * @return string|null 待機中のアカウントID。無効・期限切れなら null
     */
    public function accountId(): ?string {
        $state = $this->state();

        return $state === null ? null : $state['account_id'];
    }

    /**
     * @return VerifiedFactors ここまでに検証できた要素。待機中でなければ空
     */
    public function factors(): VerifiedFactors {
        $factors = new VerifiedFactors();

        $state = $this->state();
        if ($state === null) return $factors;

        foreach ($state['factors'] as $factor) {
            $type = CredentialType::tryFrom($factor['type']);
            if ($type === null) continue;

            $factors->add(new VerifiedFactor($type, $factor['sufficient']));
        }

        return $factors;
    }

    /**
     * @return void
     */
    public function forget(): void {
        $this->request->session()->forget(self::KEY);
    }

    /**
     * 期限切れなら捨てたうえで null を返す。
     *
     * @return array{account_id: string, factors: list<array{type: string, sufficient: bool}>}|null
     */
    private function state(): ?array {
        $state = $this->request->session()->get(self::KEY);
        if (!is_array($state)) return null;

        $accountId = $state['account_id'] ?? null;
        $factors = $state['factors'] ?? null;
        $expiresAt = $state['expires_at'] ?? null;

        if (!is_string($accountId) || !is_array($factors) || !is_int($expiresAt)) return null;

        if ($expiresAt < now()->getTimestamp()) {
            $this->forget();

            return null;
        }

        /** @var list<array{type: string, sufficient: bool}> $factors */
        return ['account_id' => $accountId, 'factors' => $factors];
    }

    /**
     * @param VerifiedFactors $factors
     * @return list<array{type: string, sufficient: bool}>
     */
    private function toArray(VerifiedFactors $factors): array {
        $result = [];
        foreach ($factors->all() as $factor) {
            $result[] = ['type' => $factor->type->value, 'sufficient' => $factor->sufficient];
        }

        return $result;
    }
}
