<?php
namespace App\Support\Registry;

use LogicException;

/**
 * 差し替え口の実装をキーで束ねる入れ物の土台。
 *
 * 登録の口 (`register()`) は型を固定したいので、各レジストリが自分で公開し、ここの `add()` を呼ぶ。
 * 引数の型を子クラスで狭めることは PHP ではできないため。
 *
 * @template T of object
 */
abstract class Registry {
    /** @var array<string, T> */
    private array $items = [];

    /**
     * 二重登録は黙って上書きされると気づけないので、起動時に落とす。
     * 実装が意図せず差し替わるのは事故なので、動く前に止める。
     *
     * @param string $key 引くときのキー
     * @param T $item 登録する実装
     * @return void
     * @throws LogicException 同じキーが既に登録されている場合
     */
    protected function add(string $key, object $item): void {
        if (isset($this->items[$key])) throw new LogicException("{$key} は既に登録されています");

        $this->items[$key] = $item;
    }

    /**
     * @param string $key 引くときのキー
     * @return T|null 未登録なら null
     */
    protected function find(string $key): ?object {
        return $this->items[$key] ?? null;
    }

    /**
     * @return array<string, T> 登録順
     */
    protected function items(): array {
        return $this->items;
    }

    /**
     * @return list<string> 登録されているキー。登録順
     */
    protected function keys(): array {
        return array_keys($this->items);
    }
}
