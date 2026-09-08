<?php
namespace App\Modules\Credential\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * メールだけでログインするためのリンク
 */
class MagicLinkMail extends Mailable {
    /**
     * @param string $loginUrl 平文トークン入りのログイン URL
     * @param int $ttlMinutes リンクの有効分数
     */
    public function __construct(
        public readonly string $loginUrl,
        public readonly int $ttlMinutes,
    ) {}

    /**
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: 'ChreeID にログインする');
    }

    /**
     * @return Content
     */
    public function content(): Content {
        return new Content(view: 'mail.magic-link', text: 'mail.text.magic-link');
    }
}
