<?php
namespace App\Console\Commands;

use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * サービス (OAuth クライアント) を登録する。
 *
 * MVP では登録画面を作らず、このコマンドだけで運用する。
 */
class RegisterOAuthClient extends Command {
    protected $signature = 'chreeid:register-client
        {name : サービス名}
        {redirect-uri* : 許可するリダイレクト先 (複数可)}
        {--scopes=openid profile email : 要求を許すスコープ}
        {--trust=unapproved : official / approved / unapproved / disabled}
        {--public : PKCE のみの public クライアントにする}';

    protected $description = 'ChreeID に接続するサービスを登録する';

    /**
     * @return int
     */
    public function handle(): int {
        $trust = ServiceTrust::tryFrom((string) $this->option('trust'));
        if ($trust === null) {
            $this->error('trust が不正です: ' . (string) $this->option('trust'));

            return self::FAILURE;
        }

        $isConfidential = !$this->option('public');
        $clientId = Str::lower(Str::ulid()->toString());
        $secret = $isConfidential ? Str::random(64) : null;

        /** @var list<string> $redirectUris */
        $redirectUris = $this->argument('redirect-uri');

        OAuthClientModel::create([
            'id' => $clientId,
            'secret_hash' => $secret === null ? null : hash('sha256', $secret),
            'name' => (string) $this->argument('name'),
            'redirect_uris' => $redirectUris,
            'scopes' => (string) $this->option('scopes'),
            'is_confidential' => $isConfidential,
            'trust' => $trust,
        ]);

        $this->info('サービスを登録しました');
        $this->line('client_id     : ' . $clientId);

        // 平文を出せるのはこの1回だけ。DB にはハッシュしか残らない
        if ($secret !== null) $this->line('client_secret : ' . $secret);

        $this->line('trust         : ' . $trust->value);
        $this->line('redirect_uris : ' . implode(', ', $redirectUris));

        return self::SUCCESS;
    }
}
