<?php
namespace App\Modules\Registry\Application;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use RuntimeException;

/**
 * 管理者が操作してよい相手かを確かめる。
 *
 * **自分自身は操作させない。** 締め出されると管理画面に戻れなくなり、
 * 直すのにサーバへ入る必要が出る。
 */
class AdminTarget {
    private readonly AuthIdentityRepository $accounts;

    public function __construct(AuthIdentityRepository $accounts) {
        $this->accounts = $accounts;
    }

    /**
     * @param string $actorId 操作している管理者のアカウントID
     * @param string $targetId 対象のアカウントID
     * @return AuthIdentity 対象
     * @throws RuntimeException 対象が居ない、または自分自身の場合
     */
    public function require(string $actorId, string $targetId): AuthIdentity {
        if ($actorId === $targetId) throw new RuntimeException(__('admin.account.cannot_operate_self'));

        $target = $this->accounts->findById($targetId);
        if ($target === null) throw new RuntimeException(__('admin.account.not_found'));

        return $target;
    }
}
