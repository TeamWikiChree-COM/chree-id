<?php
namespace App\Modules\ExternalLogin\Domain;

use App\Support\Registry\Registry;

/**
 * 使える外部 IdP の一覧。
 *
 * @extends Registry<ExternalIdp>
 */
class ExternalIdpRegistry extends Registry {
    /**
     * @param ExternalIdp $provider
     */
    public function register(ExternalIdp $provider): void {
        $this->add($provider->name(), $provider);
    }

    /**
     * @param string $name
     * @return ExternalIdp|null
     */
    public function get(string $name): ?ExternalIdp {
        return $this->find($name);
    }

    /**
     * @return list<string>
     */
    public function names(): array {
        return $this->keys();
    }

    /**
     * 設定が揃っていて、実際にログインに使える IdP。
     *
     * @return list<string>
     */
    public function usableNames(): array {
        $names = [];
        foreach ($this->items() as $name => $provider) {
            if ($provider->isConfigured()) $names[] = $name;
        }

        return $names;
    }

    /**
     * 登録されているすべての IdP の表示。
     *
     * 使えるものだけにしないのは、設定を解除した IdP でも連携済みの一覧や履歴には名前を出すため。
     *
     * @param string $locale
     * @return array<string, array{label: string, icon: string, family: string}>
     */
    public function displays(string $locale): array {
        $displays = [];
        foreach ($this->items() as $name => $provider) {
            $displays[$name] = $provider->display()->toArray($locale);
        }

        return $displays;
    }
}
