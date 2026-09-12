<?php
namespace App\Modules\Identity\Http;

use App\Modules\Identity\Application\AccountIcons;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Domain\IconSource;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * アカウントのアイコン。
 *
 * 表示は誰でも引ける。アイコンは同意画面や連携先にも出る前提のもので、
 * ログインしていないと見えないと、そちらで出せなくなる。
 */
class IconController {
    /** 受け取る画像の上限 (KB) */
    private const MAX_KILOBYTES = 2048;

    public function __construct(
        private readonly ChreeSession $session,
        private readonly AuthIdentityRepository $accounts,
        private readonly AccountIcons $icons,
    ) {}

    /**
     * アップロードした画像を返す。
     *
     * @param string $account アカウントID (ULID)
     * @return Response
     */
    public function show(string $account): Response {
        $identity = $this->accounts->findById($account);
        $contents = $identity === null ? null : $this->icons->contentsOf($identity);

        if ($identity === null || $contents === null) abort(404);

        return response($contents, 200, [
            'Content-Type' => $this->icons->mimeTypeOf($identity) ?? 'application/octet-stream',
            // 差し替えたときに古い絵が残らない程度に短く持たせる
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * アイコンを設定する。
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException 画像が要るのに無い場合
     */
    public function update(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $account = $this->accounts->findById($accountId);
        if ($account === null) return redirect('/login');

        $request->validate([
            'source' => ['required', 'string', 'in:none,gravatar,upload'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:' . self::MAX_KILOBYTES],
        ]);

        $source = IconSource::from($request->string('source')->toString());

        if ($source === IconSource::GRAVATAR && $account->email === null) {
            throw ValidationException::withMessages(['source' => 'Gravatar を使うにはメールアドレスが必要です']);
        }

        match ($source) {
            IconSource::NONE => $this->icons->clear($account),
            IconSource::GRAVATAR => $this->icons->useGravatar($account),
            IconSource::UPLOAD => $this->icons->upload($account, $this->file($request)),
        };

        return redirect('/settings')->with('iconSaved', true);
    }

    /**
     * @param Request $request
     * @return UploadedFile
     * @throws ValidationException 画像が添えられていない場合
     */
    private function file(Request $request): UploadedFile {
        $file = $request->file('icon');
        if ($file instanceof UploadedFile) return $file;

        throw ValidationException::withMessages(['icon' => '画像を選んでください']);
    }
}
