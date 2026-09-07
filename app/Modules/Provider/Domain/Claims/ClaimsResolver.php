<?php
namespace App\Modules\Provider\Domain\Claims;

use App\Modules\Identity\Domain\ChreeAccount;

/**
 * scope ひとつ分の契約。要求されたときに返すクレームを組み立てる。
 */
interface ClaimsResolver {
    /**
     * @return string 対応する scope
     */
    public function scope(): string;

    /**
     * @param ChreeAccount $account
     * @return array<string, mixed>
     */
    public function resolve(ChreeAccount $account): array;
}
