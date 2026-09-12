<?php
namespace App\Console\Commands;

use App\Modules\Registry\Application\RegisterClient;
use App\Modules\Registry\Domain\ServiceTrust;
use Illuminate\Console\Command;

/**
 * サービス (OAuth クライアント) を登録する。
 *
 * 同じことは管理画面 (/admin/clients) からもできる。登録の中身は RegisterClient にあり、
 * こちらは入力を整えて結果を表示するだけ。
 */
class RegisterOAuthClient extends Command {
    #[\Override]
    protected $signature = 'chreeid:register-client
        {name : サービス名}
        {redirect-uri* : 許可するリダイレクト先 (複数可)}
        {--scopes=openid profile email : 要求を許すスコープ}
        {--trust=unapproved : official / approved / unapproved / disabled}
        {--public : PKCE のみの public クライアントにする}';

    #[\Override]
    protected $description = 'ChreeID に接続するサービスを登録する';

    public function __construct(private readonly RegisterClient $register) {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function handle(): int {
        $trust = ServiceTrust::tryFrom((string) $this->option('trust'));
        if ($trust === null) {
            $this->error('trust が不正です: ' . (string) $this->option('trust'));

            return self::FAILURE;
        }

        /** @var list<string> $redirectUris */
        $redirectUris = $this->argument('redirect-uri');

        $registered = $this->register->execute(
            (string) $this->argument('name'),
            // CLI からは言語ごとの名前を付けない。必要なら管理画面で足す
            [],
            $redirectUris,
            (string) $this->option('scopes'),
            $trust,
            !$this->option('public'),
        );

        $this->info('サービスを登録しました');
        $this->line('client_id     : ' . $registered->client->id);

        // 平文を出せるのはこの1回だけ。DB にはハッシュしか残らない
        if ($registered->secret !== null) $this->line('client_secret : ' . $registered->secret);

        $this->line('trust         : ' . $trust->value);
        $this->line('redirect_uris : ' . implode(', ', $redirectUris));

        return self::SUCCESS;
    }
}
