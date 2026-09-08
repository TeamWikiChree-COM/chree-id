<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * ビルド成果物に依存させない。
     *
     * public/build は git 管理外で、CI では npm run build を通らないため
     * マニフェストが存在しない。素のままだと画面を描くテストが軒並み
     * 「Vite manifest not found」で落ちる。
     *
     * フロントの成果物はテストの対象ではないので、読み込み自体を差し替える。
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
