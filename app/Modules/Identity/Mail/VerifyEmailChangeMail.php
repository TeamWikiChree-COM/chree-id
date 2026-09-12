<?php
namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 変更先アドレスに送る確認リンク
 */
class VerifyEmailChangeMail extends Mailable {
    /**
     * @param string $verifyUrl 平文トークン入りの確認 URL
     * @param int $ttlMinutes リンクの有効分数
     */
    public function __construct(
        public readonly string $verifyUrl,
        public readonly int $ttlMinutes,
    ) {}

    /**
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: __('mail.verify_email_change.subject'));
    }

    /**
     * @return Content
     */
    public function content(): Content {
        return new Content(view: 'mail.verify-email-change', text: 'mail.text.verify-email-change');
    }
}
