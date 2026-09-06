<?php
namespace App\Modules\Credential\Domain;

/** Verifier 共通の結果生成ヘルパー */
trait BuildsVerificationResult {
    abstract public function type(): CredentialType;

    protected function success(): VerificationResult {
        return VerificationResult::success($this->type());
    }

    protected function failure(): VerificationResult {
        return VerificationResult::failure($this->type());
    }
}
