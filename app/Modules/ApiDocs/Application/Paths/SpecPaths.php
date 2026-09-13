<?php
namespace App\Modules\ApiDocs\Application\Paths;

/**
 * OpenAPI の paths のうち、ひとまとまりの分を出すもの。
 */
interface SpecPaths {
    /**
     * @return array<string, array<string, mixed>> パス => メソッド => 操作
     */
    public function paths(): array;
}
