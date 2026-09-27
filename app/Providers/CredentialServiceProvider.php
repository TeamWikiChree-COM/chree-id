<?php
namespace App\Providers;

use App\Modules\Credential\Domain\CredentialRegistry as Registry;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Facades\CredentialRegistry;
use App\Modules\Credential\Infrastructure\EloquentCredentialRepository;
use App\Modules\Credential\Infrastructure\Verifiers\MagicLinkVerifier;
use App\Modules\Credential\Infrastructure\Verifiers\PasskeyVerifier;
use App\Modules\Credential\Infrastructure\Verifiers\PasswordVerifier;
use App\Modules\Credential\Infrastructure\Verifiers\RecoveryCodeVerifier;
use App\Modules\Credential\Infrastructure\Verifiers\TotpVerifier;
use Illuminate\Support\ServiceProvider;

/**
 * モジュール起動時の初期設定、認証系の登録
 */
class CredentialServiceProvider extends ServiceProvider {
    /**
     * 一覧は空で用意するだけ。中身は boot() で、プラグインと同じ Facade から入れる。
     */
    public function register(): void {
        $this->app->bind(CredentialRepository::class, EloquentCredentialRepository::class);
        $this->app->singleton(Registry::class);
    }

    /**
     * 認証方式の一覧 (増やすときはここに1行)
     */
    public function boot(): void {
        CredentialRegistry::register($this->app->make(PasswordVerifier::class));
        CredentialRegistry::register($this->app->make(MagicLinkVerifier::class));
        CredentialRegistry::register($this->app->make(TotpVerifier::class));
        CredentialRegistry::register($this->app->make(RecoveryCodeVerifier::class));
        CredentialRegistry::register($this->app->make(PasskeyVerifier::class));
    }
}
