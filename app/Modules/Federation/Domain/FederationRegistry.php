<?php
namespace App\Modules\Federation\Domain;

use LogicException;

/**
 * 使える外部 IdP の一覧。
 *
 * 増やすときは Provider クラスを1つ書いて、ここに1行足す。
 */
class FederationRegistry {
    /** @var array<string, FederationProvider> */
    private array $providers = [];

    /**
     * @param FederationProvider $provider
     * @return void
     */
    public function register(FederationProvider $provider): void {
        $name = $provider->name();
        if (isset($this->providers[$name])) throw new LogicException("{$name} は既に登録されています");

        $this->providers[$name] = $provider;
    }

    /**
     * @param string $name
     * @return FederationProvider|null
     */
    public function get(string $name): ?FederationProvider {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array {
        return array_keys($this->providers);
    }
}
