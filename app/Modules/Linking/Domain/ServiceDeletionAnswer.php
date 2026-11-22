<?php
namespace App\Modules\Linking\Domain;

/**
 * 削除の問い合わせ・依頼に対するサービスの返答
 */
final class ServiceDeletionAnswer {
    /** 削除できる (依頼なら受け付けた) か */
    public readonly bool $accepted;

    /** 断った理由。サービスが利用者向けに書いた文言 */
    public readonly ?string $reason;

    /** 物理削除までの日数。サービスが答えなければ null */
    public readonly ?int $graceDays;

    public function __construct(bool $accepted, ?string $reason, ?int $graceDays) {
        $this->accepted = $accepted;
        $this->reason = $reason;
        $this->graceDays = $graceDays;
    }

    /**
     * @param array<mixed> $body サービスが返した JSON
     * @return self|null 形式が合わなければ null
     */
    public static function fromResponse(array $body): ?self {
        $accepted = $body['accepted'] ?? null;
        if (!is_bool($accepted)) return null;

        $reason = $body['reason'] ?? null;
        $graceDays = $body['grace_days'] ?? null;

        return new self(
            $accepted,
            is_string($reason) && $reason !== '' ? mb_substr($reason, 0, 500) : null,
            is_int($graceDays) && $graceDays >= 0 ? $graceDays : null,
        );
    }

    /**
     * @return array{accepted: bool, reason: string|null, graceDays: int|null}
     */
    public function toArray(): array {
        return ['accepted' => $this->accepted, 'reason' => $this->reason, 'graceDays' => $this->graceDays];
    }
}
