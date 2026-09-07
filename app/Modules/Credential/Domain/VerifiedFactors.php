<?php
namespace App\Modules\Credential\Domain;

/**
 * ひとつのログイン試行で検証に成功した要素の集まり。
 *
 * 同じ方式を2回通しても1要素として扱う (パスワードを2回入れても2要素にはならない)。
 */
class VerifiedFactors {
    /** @var array<string, VerifiedFactor> */
    private array $factors = [];

    /**
     * @param VerifiedFactor $factor
     * @return void
     */
    public function add(VerifiedFactor $factor): void {
        $this->factors[$factor->type->value] = $factor;
    }

    /** @return int */
    public function count(): int {
        return count($this->factors);
    }

    /** @return bool */
    public function hasAny(): bool {
        return $this->factors !== [];
    }

    /**
     * 単独で多要素を満たす要素があるか (パスキーなど)
     *
     * @return bool
     */
    public function hasSufficientSingleFactor(): bool {
        foreach ($this->factors as $factor) {
            if ($factor->sufficient) return true;
        }

        return false;
    }

    /**
     * @param CredentialType $type
     * @return bool
     */
    public function has(CredentialType $type): bool {
        return isset($this->factors[$type->value]);
    }

    /** @return array<string, VerifiedFactor> */
    public function all(): array {
        return $this->factors;
    }
}
