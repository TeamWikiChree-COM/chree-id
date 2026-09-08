<?php
namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 変更前のアドレスに送る通知。
 *
 * 乗っ取られたときに本人が気付ける唯一の経路なので、変更が成立する前に送る。
 */
class EmailChangeNoticeMail extends Mailable {
    /**
     * @param string $newEmail 変更先アドレス
     */
    public function __construct(public readonly string $newEmail) {}

    /**
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: 'ChreeID のメールアドレス変更が申し込まれました');
    }

    /**
     * @return Content
     */
    public function content(): Content {
        return new Content(view: 'mail.email-change-notice', text: 'mail.text.email-change-notice');
    }
}
