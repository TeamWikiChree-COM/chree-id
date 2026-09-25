<?php
namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 追加アドレスに送る確認リンク
 */
class VerifyAccountEmailMail extends Mailable {
    public readonly string $verifyUrl;
    public readonly int $ttlMinutes;

    /**
     * @param string $verifyUrl 平文トークン入りの確認 URL
     * @param int $ttlMinutes リンクの有効分数
     */
    public function __construct(string $verifyUrl, int $ttlMinutes) {
        $this->verifyUrl = $verifyUrl;
        $this->ttlMinutes = $ttlMinutes;
    }

    /**
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: __('mail.verify_account_email.subject'));
    }

    /**
     * @return Content
     */
    public function content(): Content {
        return new Content(view: 'mail.verify-account-email', text: 'mail.text.verify-account-email');
    }
}
