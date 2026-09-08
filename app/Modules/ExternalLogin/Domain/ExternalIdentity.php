<?php
namespace App\Modules\ExternalLogin\Domain;

/**
 * 外部 IdP が「この人は誰か」と主張してきた内容
 */
readonly class ExternalIdentity {
    public string $provider;
    public string $subject;
    public ?string $email;
    public bool $emailVerified;
    public ?string $displayName;

    /**
     * @param string $provider 'google' など
     * @param string $subject IdP 側の一意なID
     * @param string|null $email
     * @param bool $emailVerified IdP がメールを検証済みとしているか
     * @param string|null $displayName
     */
    public function __construct(
        string $provider,
        string $subject,
        ?string $email,
        bool $emailVerified,
        ?string $displayName,
    ) {
        $this->provider = $provider;
        $this->subject = $subject;
        $this->email = $email;
        $this->emailVerified = $emailVerified;
        $this->displayName = $displayName;
    }

    /**
     * credentials.identifier に入れる値。
     * プロバイダ名を前置しないと、別IdPの同じIDと衝突する。
     *
     * @return string
     */
    public function credentialIdentifier(): string {
        return $this->provider . ':' . $this->subject;
    }
}
