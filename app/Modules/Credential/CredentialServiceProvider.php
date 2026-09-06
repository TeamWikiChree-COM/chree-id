<?php
namespace App\Modules\Credential;

use App\Modules\Credential\Domain\CredentialRegistry;
use App\Modules\Credential\Infrastructure\Verifiers\PasswordVerifier;
use Illuminate\Support\ServiceProvider;

/**
 * モジュール起動時の初期設定、認証系の登録
 */
class CredentialServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->app->singleton(CredentialRegistry::class, function ($app) {
            $registry = new CredentialRegistry();
            $registry->register($app->make(PasswordVerifier::class));

            return $registry;
        });
    }
}