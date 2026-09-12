<?php

use App\Modules\Credential\Mail\MagicLinkMail;
use App\Modules\Credential\Mail\PasswordResetMail;
use App\Modules\Identity\Mail\EmailChangeNoticeMail;
use App\Modules\Identity\Mail\RegistrationExistsMail;
use App\Modules\Identity\Mail\VerifyEmailChangeMail;
use App\Modules\Identity\Mail\VerifyEmailMail;
use App\Modules\Identity\Mail\VerifyRegistrationMail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

/**
 * メールが実際に組み上がることを確かめる。
 *
 * **他のテストは `Mail::fake()` を使うので Blade を描画しない。**
 * 送ったことは分かっても、テンプレートが壊れていることには気づけない。
 * 文言を辞書へ出したあとは、キーの打ち間違いがここでしか出ない。
 *
 * `render()` では HTML 版しか通らないので、array トランスポートに実際に流して
 * **テキスト版と件名も含めた MIME を見る**。HTML だけ直して片方を壊すのが
 * いちばん起きやすい。
 */
class MailRenderingTest extends \Tests\TestCase {
    /**
     * @return array<string, array{Mailable, list<string>}>
     */
    public static function mailables(): array {
        $url = 'https://id.example.com/verify/abc123';

        return [
            'magic link' => [new MagicLinkMail($url, 15), [$url, '15']],
            'password reset' => [new PasswordResetMail($url, 30), [$url, '30']],
            'verify registration' => [new VerifyRegistrationMail($url, 60), [$url, '60']],
            'verify email' => [new VerifyEmailMail($url, 60), [$url, '60']],
            'verify email change' => [new VerifyEmailChangeMail($url, 60), [$url, '60']],
            'registration exists' => [new RegistrationExistsMail($url), [$url]],
            'email change notice' => [new EmailChangeNoticeMail('new@example.com'), ['new@example.com']],
        ];
    }

    /**
     * @param Mailable $mail 組み立てるメール
     * @param list<string> $expected HTML 版とテキスト版の両方に出ていなければならない値
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('mailables')]
    public function test_buildsBothBodiesAndASubject(Mailable $mail, array $expected): void {
        $email = $this->send($mail);

        $subject = $email->getSubject();
        $html = (string) $email->getHtmlBody();
        $text = (string) $email->getTextBody();

        $this->assertNotNull($subject);
        $this->assertNotSame('', trim(strip_tags($html)));
        $this->assertNotSame('', trim($text));

        // 引けなかったキーは Laravel がキー名をそのまま返すので、本文に紛れ込む
        foreach (['subject' => $subject, 'html' => $html, 'text' => $text] as $where => $body) {
            $this->assertStringNotContainsString('mail.', $body, "{$where} に引けていないキーがある");
        }

        foreach ($expected as $value) {
            $this->assertStringContainsString($value, $html, 'HTML 版に値が出ていない');
            $this->assertStringContainsString($value, $text, 'テキスト版に値が出ていない');
        }
    }

    /**
     * @param Mailable $mail 送るメール
     * @return Email 組み上がった MIME
     */
    private function send(Mailable $mail): Email {
        config(['mail.default' => 'array']);

        Mail::to('someone@example.com')->send($mail);

        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        $sent = $transport->messages()->last();
        $this->assertInstanceOf(SentMessage::class, $sent);

        $message = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $message);

        return $message;
    }
}
