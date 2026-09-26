<?php
namespace App\Support\Registry;

/**
 * 中身を自分で探して埋めるレジストリの土台。
 *
 * Registry は ServiceProvider が1件ずつ登録するが、こちらは決まった場所を探して集める。
 * 置くだけで読み込まれる形にしたいもの (プラグインなど) に使う。
 * 本体側に一覧を書き足す手順があると、足すたびに本体へ差分が出てしまうため。
 *
 * @template T of object
 * @extends Registry<T>
 */
abstract class DynamicRegistry extends Registry {
    private bool $loaded = false;

    /**
     * 登録するものを探す。
     *
     * @return iterable<T>
     */
    abstract protected function discover(): iterable;

    /**
     * @param T $item 見つけたもの
     * @return string 引くときのキー
     */
    abstract protected function keyOf(object $item): string;

    /**
     * 探して登録する。2回目以降は何もしない。
     *
     * @return void
     */
    protected function load(): void {
        if ($this->loaded) return;

        foreach ($this->discover() as $item) {
            $this->add($this->keyOf($item), $item);
        }
        $this->loaded = true;
    }
}
