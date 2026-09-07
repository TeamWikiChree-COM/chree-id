<?php
namespace App\Providers;

use App\Modules\Credential\Domain\CredentialRegistry;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Infrastructure\EloquentCredentialRepository;
use App\Modules\Credential\Infrastructure\Verifiers\MagicLinkVerifier;
use App\Modules\Credential\Infrastructure\Verifiers\PasswordVerifier;
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

        $this->app->singleton(CredentialRegistry::class, function ($app) {
            $registry = new CredentialRegistry();

            // 認証方式の一覧 (増やすときはここに1行)
            $registry->register($app->make(PasswordVerifier::class));
            $registry->register($app->make(MagicLinkVerifier::class));

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
