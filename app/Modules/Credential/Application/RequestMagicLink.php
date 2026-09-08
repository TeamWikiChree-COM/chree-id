<?php
namespace App\Modules\Credential\Application;

use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Mail\MagicLinkMail;
use App\Modules\Identity\Domain\ChreeAccountRepository;
use Illuminate\Support\Facades\Mail;

/**
 * メールでログインするためのリンクを送る。
 *
 * 有効化していないアカウントや存在しないアドレスでも呼び出し側への戻り値を変えないのは、
 * 画面の応答からアドレスの存在を推測させないため。
 */
class RequestMagicLink {
    /** リンクの有効分数。IssueMagicLink 側の寿命と揃えて画面に出す */
    public const EXPIRES_MINUTES = 15;

    public function __construct(
        private readonly ChreeAccountRepository $accounts,
        private readonly CredentialRepository $credentials,
        private readonly IssueMagicLink $issue,
    ) {}

    /**
     * @param string $email メールアドレス
     * @return void
     */
    public function execute(string $email): void {
        $account = $this->accounts->findByEmail($email);
        if ($account === null || $account->isSuspended()) return;

        // 有効化していないアカウントに送ると「メールが届く = 登録済み」が漏れる、の前に
        // そもそもトークンを照合できない
        if (!$this->credentials->has($account->id, CredentialType::MAGIC_LINK)) return;

        $token = $this->issue->execute($account->id);

        Mail::to($email)->send(new MagicLinkMail(url("/login/magic/{$token}"), self::EXPIRES_MINUTES));
    }
}
