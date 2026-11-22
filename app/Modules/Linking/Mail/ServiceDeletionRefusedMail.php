<?php
namespace App\Modules\Linking\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 退会時に依頼したサービス側の削除が断られたことを知らせる。
 *
 * 本人は退会済みで ChreeID の画面を見られないので、メールでしか伝わらない。
 */
class ServiceDeletionRefusedMail extends Mailable {
    /**
     * @param string $serviceName サービスの表示名
     * @param string|null $reason サービスが返した理由
     */
    public function __construct(public readonly string $serviceName, public readonly ?string $reason) {}

    /**
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: __('mail.service_deletion_refused.subject', ['service' => $this->serviceName]));
    }

    /**
     * @return Content
     */
    public function content(): Content {
        return new Content(view: 'mail.service-deletion-refused', text: 'mail.text.service-deletion-refused');
    }
}
