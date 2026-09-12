<?php
namespace App\Support\Locale;

use App\Modules\Identity\Infrastructure\AuthIdentityModel;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\Request;

/**
 * ログイン中の本人が選んだ表示言語の読み書き。
 *
 * 認証主体の行に持たせているので、端末を変えても付いてくる。
 * **null は「選んでいない」であって「日本語」ではない。** 空にすれば
 * ブラウザの設定に戻る、という操作ができるようにしてある。
 */
final class StoredLocale {
    public function __construct(
        private readonly ChreeSession $session,
        private readonly Request $request,
    ) {}

    /**
     * @return string|null 選んでいなければ null
     */
    public function forCurrentUser(): ?string {
        $account = $this->account();

        return $account?->locale;
    }

    /**
     * @param string|null $locale 選んだ言語。null で「選んでいない」に戻す
     * @return void
     */
    public function remember(?string $locale): void {
        $this->account()?->forceFill(['locale' => $locale])->save();
    }

    /**
     * @return AuthIdentityModel|null 未ログインなら null
     */
    private function account(): ?AuthIdentityModel {
        // セッションが張られていない経路 (サーバ間 API など) から呼ばれることがある。
        // ChreeSession は session() を無条件に触るので、先に見ておく
        if (!$this->request->hasSession()) return null;

        $id = $this->session->accountId();

        return $id === null ? null : AuthIdentityModel::query()->find($id);
    }
}
