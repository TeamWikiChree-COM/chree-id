<?php
namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\AuthIdentity;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Domain\IconSource;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * アカウントのアイコン。
 *
 * 画像は公開ディレクトリに置かず、取り出しは IconController を通す。
 * 置いた瞬間に URL が推測できてしまうと、退会後も画像だけ残って参照できる。
 */
class AccountIcons {
    /** アップロードの置き場 (非公開ディスクの中) */
    private const DIRECTORY = 'account-icons';

    public function __construct(private readonly AuthIdentityRepository $accounts) {}

    /**
     * 画面に出す URL。
     *
     * Gravatar は毎回メールアドレスから導出する。保存してしまうと、
     * メールを変えたときに古いアドレスの絵が残る。
     *
     * @param AuthIdentity $account 対象のアカウント
     * @return string|null 未設定なら null (画面側は頭文字などで代替する)
     */
    public function urlFor(AuthIdentity $account): ?string {
        return match ($account->iconSource) {
            IconSource::NONE => null,
            IconSource::GRAVATAR => $this->gravatarUrl($account->email),
            IconSource::UPLOAD => $account->iconPath === null ? null : "/profile/icon/{$account->id}",
        };
    }

    /**
     * アップロードした画像に差し替える。
     *
     * @param AuthIdentity $account 対象のアカウント
     * @param UploadedFile $file 受け取った画像
     * @return void
     */
    public function upload(AuthIdentity $account, UploadedFile $file): void {
        $path = $file->store(self::DIRECTORY, ['disk' => 'local']);
        if (!is_string($path)) return;

        $this->deleteFile($account);
        $this->accounts->updateIcon($account->id, IconSource::UPLOAD, $path);
    }

    /**
     * Gravatar に切り替える。アップロード済みの画像は残さない。
     *
     * @param AuthIdentity $account 対象のアカウント
     * @return void
     */
    public function useGravatar(AuthIdentity $account): void {
        $this->deleteFile($account);
        $this->accounts->updateIcon($account->id, IconSource::GRAVATAR, null);
    }

    /**
     * アイコンを未設定に戻す。
     *
     * @param AuthIdentity $account 対象のアカウント
     * @return void
     */
    public function clear(AuthIdentity $account): void {
        $this->deleteFile($account);
        $this->accounts->updateIcon($account->id, IconSource::NONE, null);
    }

    /**
     * アップロードした画像の中身を取り出す。
     *
     * @param AuthIdentity $account 対象のアカウント
     * @return string|null 持っていなければ null
     */
    public function contentsOf(AuthIdentity $account): ?string {
        if ($account->iconSource !== IconSource::UPLOAD || $account->iconPath === null) return null;

        $disk = $this->disk();
        if (!$disk->exists($account->iconPath)) return null;

        $contents = $disk->get($account->iconPath);

        return is_string($contents) ? $contents : null;
    }

    /**
     * @param AuthIdentity $account 対象のアカウント
     * @return string|null 保存されている MIME タイプ
     */
    public function mimeTypeOf(AuthIdentity $account): ?string {
        if ($account->iconPath === null) return null;

        $mime = $this->disk()->mimeType($account->iconPath);

        return is_string($mime) ? $mime : null;
    }

    /**
     * メールアドレスから Gravatar の URL を組み立てる。
     *
     * 画像が無いアドレスでは 404 を返させる (d=404)。既定の似顔絵を出すと、
     * 設定した覚えのない絵が出てきて不審に見える。
     *
     * @param string|null $email メールアドレス
     * @return string|null メールを持たなければ null
     */
    private function gravatarUrl(?string $email): ?string {
        if ($email === null || $email === '') return null;

        $hash = hash('sha256', strtolower(trim($email)));

        return "https://www.gravatar.com/avatar/{$hash}?s=200&d=404";
    }

    /**
     * @param AuthIdentity $account 対象のアカウント
     * @return void
     */
    private function deleteFile(AuthIdentity $account): void {
        if ($account->iconPath === null) return;

        $this->disk()->delete($account->iconPath);
    }

    /**
     * @return Filesystem 非公開ディスク
     */
    private function disk(): Filesystem {
        return Storage::disk('local');
    }
}
