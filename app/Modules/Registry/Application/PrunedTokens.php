<?php
namespace App\Modules\Registry\Application;

/**
 * 掃除で消した件数の内訳。
 *
 * 合計だけだと「何が溜まっていたのか」が分からない。
 * 期限切れの登録申し込みが積もっているのと、使用済みトークンが積もっているのでは
 * 疑うところが違うので、種類ごとに持つ。
 */
class PrunedTokens {

    public readonly int $registrations;
    public readonly int $emailChanges;
    public readonly int $expiredTokens;
    public readonly int $usedTokens;

    public function __construct(int $registrations, int $emailChanges, int $expiredTokens, int $usedTokens) {
        $this->registrations = $registrations;
        $this->emailChanges = $emailChanges;
        $this->expiredTokens = $expiredTokens;
        $this->usedTokens = $usedTokens;
    }

    /**
     * @return int 消した総数
     */
    public function total(): int {
        return $this->registrations + $this->emailChanges + $this->expiredTokens + $this->usedTokens;
    }
}
