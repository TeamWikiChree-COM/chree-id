<?php
namespace Tests\Concerns;

use App\Modules\Identity\Domain\AccountOrigin;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use Illuminate\Support\Facades\Config;

/**
 * 管理画面のテストで、管理者としてログインした状態を作る。
 */
trait LogsInAsAdmin {
    /**
     * @return void
     */
    private function loginAsAdmin(): void {
        $email = 'admin@example.com';
        Config::set('chreeid.admin_emails', [$email]);

        $accounts = app(AuthIdentityRepository::class);
        $account = $accounts->create(AccountOrigin::USER, $email, '管理者');
        $accounts->markEmailVerified($account->id);

        $this->withSession(['chreeid.account_id' => $account->id]);
    }
}
