<?php
namespace App\Modules\Provider\Application;

use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Provider\Domain\SignInStepKind;
use Illuminate\Support\Collection;

/**
 * DecideSignIn の結果。
 */
readonly class SignInStep {
    public SignInStepKind $kind;

    /** GRANTED のときだけ入る */
    public ?ServiceAccountModel $serviceAccount;

    /** @var Collection<int, ServiceAccountModel> CHOOSE_ACCOUNT のときだけ中身がある */
    public Collection $candidates;

    /**
     * @param SignInStepKind $kind
     * @param ServiceAccountModel|null $serviceAccount
     * @param Collection<int, ServiceAccountModel>|null $candidates
     */
    private function __construct(SignInStepKind $kind, ?ServiceAccountModel $serviceAccount = null, ?Collection $candidates = null) {
        $this->kind = $kind;
        $this->serviceAccount = $serviceAccount;
        $this->candidates = $candidates ?? new Collection();
    }

    /**
     * @param SignInStepKind $kind LOGIN・UNCLAIMED・CONSENT のどれか
     * @return self
     */
    public static function of(SignInStepKind $kind): self {
        return new self($kind);
    }

    /**
     * @param Collection<int, ServiceAccountModel> $candidates
     * @return self
     */
    public static function chooseAccount(Collection $candidates): self {
        return new self(SignInStepKind::CHOOSE_ACCOUNT, null, $candidates);
    }

    /**
     * @param ServiceAccountModel $serviceAccount
     * @return self
     */
    public static function granted(ServiceAccountModel $serviceAccount): self {
        return new self(SignInStepKind::GRANTED, $serviceAccount);
    }
}
