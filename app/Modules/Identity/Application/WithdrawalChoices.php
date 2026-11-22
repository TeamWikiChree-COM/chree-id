<?php
namespace App\Modules\Identity\Application;

/**
 * 退会のときに、連携サービスごとにどうするかの選択。
 *
 * 削除するものはサービスへ削除を依頼し、解放するものは新しい認証主体へ分離する。
 */
final class WithdrawalChoices {
    /** @var list<string> 削除を依頼するサービスアカウントのID */
    public readonly array $deletions;

    /** @var array<string, list<string>> 解放するサービスアカウントのIDと、持っていく認証手段のID */
    public readonly array $releases;

    /**
     * @param list<string> $deletions
     * @param array<string, list<string>> $releases
     */
    public function __construct(array $deletions = [], array $releases = []) {
        $this->deletions = $deletions;
        $this->releases = $releases;
    }

    /**
     * @return list<string> 選択済みのサービスアカウントのID
     */
    public function chosen(): array {
        return [...$this->deletions, ...array_map('strval', array_keys($this->releases))];
    }
}
