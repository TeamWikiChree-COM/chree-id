<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;

/**
 * メールアドレスから認証主体を決める。
 *
 * **メールは認証主体を一意に決めない。** 同じ人が同一サービスに複数の
 * サービスアカウントを持つとき、そこには普通同じアドレスを使う
 * (ARCHITECTURE.md 8.3)。メールを持たない利用者も 26% いる。
 *
 * そこで規則をこう置く。
 *
 * - **メールが指すのは UserAccount。** ChreeID 本体へのログインや
 *   パスワード再設定は、束ねる人格を持つ認証主体を相手にする
 * - **サービスアカウントはサービス経由で指す。** どのサービスから来たかで絞れるので、
 *   メールで一意に決める必要がない
 */
class ResolveByEmail {
    public function __construct(
        private readonly AuthIdentityRepository $accounts,
        private readonly UserAccounts $userAccounts,
    ) {}

    /**
     * ChreeID 本体を相手にするときの1つを決める。
     *
     * 束ねる人格を持つものを優先する。無ければ候補が1つのときだけ返す。
     * 複数のサービスアカウントが同じアドレスを使っている状態では、
     * どれを指すかを決められないので null を返す。
     *
     * @param string $email メールアドレス
     * @return AuthIdentity|null 決められなければ null
     */
    public function primary(string $email): ?AuthIdentity {
        $candidates = $this->candidates($email);

        foreach ($candidates as $candidate) {
            if ($this->userAccounts->exists($candidate->id)) return $candidate;
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * 束ねる人格を持つものがあるか。登録の重複判定に使う。
     *
     * @param string $email メールアドレス
     * @return bool
     */
    public function hasUserAccount(string $email): bool {
        foreach ($this->candidates($email) as $candidate) {
            if ($this->userAccounts->exists($candidate->id)) return true;
        }

        return false;
    }

    /**
     * @param string $email メールアドレス
     * @return list<AuthIdentity> 停止されていない候補
     */
    public function candidates(string $email): array {
        $usable = [];

        foreach ($this->accounts->findAllByEmail($email) as $candidate) {
            if (!$candidate->isSuspended()) $usable[] = $candidate;
        }

        return $usable;
    }
}
