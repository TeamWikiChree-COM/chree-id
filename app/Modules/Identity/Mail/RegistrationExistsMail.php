<?php
namespace App\Modules\Identity\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 既にアカウントがあるアドレスで登録が試みられたときのメール。
 *
 * 画面上は新規登録と同じ応答を返すため、「既に登録済み」であることは
 * そのアドレスの持ち主にしか伝わらない。アドレスの存在確認に使わせないための造り。
 */
class RegistrationExistsMail extends Mailable {
    /**
     * @param string $loginUrl ログイン画面の URL
     */
    public function __construct(public readonly string $loginUrl) {}

    /**
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(subject: 'ChreeID のアカウント作成を続ける');
    }

    /**
     * @return Content
     */
    public function content(): Content {
        return new Content(view: 'mail.registration-exists', text: 'mail.text.registration-exists');
    }
}
