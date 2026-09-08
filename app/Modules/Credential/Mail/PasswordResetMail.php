<?php
namespace App\Modules\Credential\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * パスワード再設定のリンク
 */
class PasswordResetMail extends Mailable {
    /**
     * @param string $resetUrl 平文トークン入りの再設定 URL
     * @param int $ttlMinutes リンクの有効分数
     */
    public function __construct(
        public readonly string $resetUrl,
        public readonly int $ttlMinutes,
    ) {}

    /**
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: 'ChreeID のパスワードを再設定する');
    }

    /**
     * @return Content
     */
    public function content(): Content {
        return new Content(text: 'mail.password-reset');
    }
}
