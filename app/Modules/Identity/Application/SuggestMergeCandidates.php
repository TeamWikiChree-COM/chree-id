<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;

/**
 * 「これはあなたの別のアカウントかもしれません」を挙げる。
 *
 * **提示と実行は別処理** (ARCHITECTURE.md 8.7)。ここは候補を並べるだけで、
 * 統合は本人の操作を通す。**メールが一致していても勝手に統合しない。**
 *
 * 候補の判定は弱い根拠でよい。同じアドレスを使っているだけの別人ということも
 * 普通にあるので、ここで挙がったからといって同一人物とは限らない。
 * 実行の根拠は「両側を証明できているか」であって、この一覧ではない。
 */
class SuggestMergeCandidates {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly ResolveByEmail $byEmail,
        private readonly UserAccounts $userAccounts,
    ) {}

    /**
     * @param string $identityId いま見ている認証主体のID (ULID)
     * @return list<array{id: string, displayName: string|null, email: string|null, hasUserAccount: bool}>
     */
    public function execute(string $identityId): array {
        $account = $this->accounts->findById($identityId);
        if ($account === null || $account->email === null) return [];

        $candidates = [];

        foreach ($this->byEmail->candidates($account->email) as $other) {
            if ($other->id === $identityId) continue;

            $candidates[] = $this->describe($other);
        }

        return $candidates;
    }

    /**
     * @param AuthIdentity $candidate 候補
     * @return array{id: string, displayName: string|null, email: string|null, hasUserAccount: bool}
     */
    private function describe(AuthIdentity $candidate): array {
        return [
            'id' => $candidate->id,
            'displayName' => $candidate->displayName,
            'email' => $candidate->email,
            // 相手も束ねる人格を持っているなら、寄せる向きを本人が決めることになる
            'hasUserAccount' => $this->userAccounts->exists($candidate->id),
        ];
    }
}
