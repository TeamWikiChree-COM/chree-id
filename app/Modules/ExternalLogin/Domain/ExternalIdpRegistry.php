<?php
namespace App\Modules\ExternalLogin\Domain;

use LogicException;

/**
 * 使える外部 IdP の一覧。
 *
 * 増やすときは ExternalIdp の実装を1つ書いて、ここに1行足す。
 */
class ExternalIdpRegistry {
    /** @var array<string, ExternalIdp> */
    private array $providers = [];

    /**
     * @param ExternalIdp $provider
     * @return void
     */
    public function register(ExternalIdp $provider): void {
        $name = $provider->name();
        if (isset($this->providers[$name])) throw new LogicException("{$name} は既に登録されています");

        $this->providers[$name] = $provider;
    }

    /**
     * @param string $name
     * @return ExternalIdp|null
     */
    public function get(string $name): ?ExternalIdp {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array {
        return array_keys($this->providers);
    }

    /**
     * 設定が揃っていて、実際にログインに使える IdP。
     *
     * @return list<string>
     */
    public function usableNames(): array {
        $names = [];
        foreach ($this->providers as $name => $provider) {
            if ($provider->isConfigured()) $names[] = $name;
        }

        return $names;
    }
}
