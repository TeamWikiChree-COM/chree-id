<?php
namespace App\Modules\Identity\Domain;

/**
 * 「+」付きアドレス (aaa+bbb@example.com) の扱い。
 *
 * 「+」以降を無視するかは受け取る側のメールサーバーが決めることで、全ドメインで通じるわけではない。
 * 通じると分かっているドメインの一覧を受け取り、その中でだけ同じ受信箱とみなす。
 */
final class PlusAddress {
    /** @var list<string> */
    private readonly array $domains;

    /**
     * @param list<string> $domains 「+」以降を無視すると分かっているドメイン
     */
    public function __construct(array $domains) {
        $this->domains = array_map(strtolower(...), $domains);
    }

    /**
     * @param string $email メールアドレス
     * @return bool このアドレスのドメインで「+」付きを同じ受信箱とみなせるか
     */
    public function supports(string $email): bool {
        $at = strrpos($email, '@');
        if ($at === false) return false;

        return in_array(strtolower(substr($email, $at + 1)), $this->domains, true);
    }

    /**
     * 「+」以降を落とした形。対応していないドメインではそのまま返す。
     *
     * 大文字小文字は揃える。比較にしか使わないので、送り先には使わないこと。
     *
     * @param string $email メールアドレス
     * @return string
     */
    public function base(string $email): string {
        $email = strtolower($email);
        if (!$this->supports($email)) return $email;

        $at = strrpos($email, '@');
        $local = substr($email, 0, (int) $at);
        $plus = strpos($local, '+');
        if ($plus === false) return $email;

        return substr($local, 0, $plus) . substr($email, (int) $at);
    }

    /**
     * @param string $email 使いたいアドレス
     * @param string $owned 本人のものと確かめたアドレス
     * @return bool 同じ受信箱に届くとみなせるか
     */
    public function sameInbox(string $email, string $owned): bool {
        return $this->base($email) === $this->base($owned);
    }
}
