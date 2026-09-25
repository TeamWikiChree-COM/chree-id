<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\CredentialServiceProvider::class,
    App\Providers\IdentityServiceProvider::class,
    App\Providers\ExternalLoginServiceProvider::class,
    App\Providers\OidcServiceProvider::class,
    App\Providers\RateLimitServiceProvider::class,
    // 本体の登録が終わってから読む。プラグインは本体のサービスを使う側
    App\Providers\PluginServiceProvider::class,
];
