<?php
namespace App\Providers;

use App\Modules\Credential\Domain\CredentialRegistry;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Infrastructure\EloquentCredentialRepository;
use App\Modules\Credential\Infrastructure\Verifiers\MagicLinkVerifier;
use App\Modules\Credential\Infrastructure\Verifiers\PasswordVerifier;
use App\Modules\Credential\Infrastructure\Verifiers\TotpVerifier;
use Illuminate\Support\ServiceProvider;

/**
 * モジュール起動時の初期設定、認証系の登録
 */
class CredentialServiceProvider extends ServiceProvider {
    /**
     * 認証タイプの登録
     */
    public function register(): void {
        $this->app->bind(CredentialRepository::class, EloquentCredentialRepository::class);

        $this->app->singleton(CredentialRegistry::class, function (): CredentialRegistry {
            $registry = new CredentialRegistry();

            // 認証方式の一覧 (増やすときはここに1行)
            $registry->register($this->app->make(PasswordVerifier::class));
            $registry->register($this->app->make(MagicLinkVerifier::class));
            $registry->register($this->app->make(TotpVerifier::class));

            return $registry;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {
        //
    }
}
