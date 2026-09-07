<?php
namespace App\Modules\Credential\Domain;

/**
 * 認証成立の判定。
 *
 * ここだけは差し替え不可で、すべての認証経路がここを通る。
 * final にしているのは「継承して2FAを迂回する」を言語レベルで禁止するため。
 *
 * 拡張ポイントは入口 (何で認証するか = Verifier) に置き、
 * 出口 (認証されたか) には置かない、という方針による。
 */
final class AuthenticationPolicy {
    /**
     * 認証が成立しているか判定する。
     *
     * @param bool $requiresSecondFactor アカウントが2要素認証を必須としているか
     * @param VerifiedFactors $factors 検証に成功した要素
     * @return bool 成立していれば true
     */
    public function isSatisfied(bool $requiresSecondFactor, VerifiedFactors $factors): bool {
        // パスキーのように単独で多要素を満たす方式は、2FA が必須でも1つで通る
        if ($factors->hasSufficientSingleFactor()) return true;

        // 2FA を有効にしていないアカウントは、パスワード1つでログインできる
        if (!$requiresSecondFactor) return $factors->hasAny();

        return $factors->count() >= 2;
    }
}
