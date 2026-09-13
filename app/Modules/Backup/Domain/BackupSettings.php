<?php
namespace App\Modules\Backup\Domain;

/**
 * `config/chreeid.php` の backup をここだけが読む。
 *
 * 設定は素のままだと何型か分からないので、**形を知る場所を1つにする**。
 * 各所で読むと、綴り間違いも型の思い込みも散らばる。
 */
final class BackupSettings {
    /**
     * @return string|null base64 の鍵。未設定なら null
     */
    public function key(): ?string {
        return $this->text('chreeid.backup.key');
    }

    /**
     * @return int 残す世代数。最低でも1世代は残す
     */
    public function keep(): int {
        $keep = config('chreeid.backup.keep');

        return is_int($keep) && $keep > 0 ? $keep : 1;
    }

    /**
     * @return bool サーバ内にも置くか
     */
    public function localEnabled(): bool {
        return config('chreeid.backup.local.enabled') === true;
    }

    /**
     * @return int サーバ内に残す日数。最低でも1日は残す
     */
    public function localDays(): int {
        $days = config('chreeid.backup.local.days');

        return is_int($days) && $days > 0 ? $days : 1;
    }

    /**
     * @return string サーバ内の置き場
     */
    public function localPath(): string {
        return $this->text('chreeid.backup.local.path') ?? storage_path('app/private/backups');
    }

    /**
     * @return string|null
     */
    public function driveClientId(): ?string {
        return $this->text('chreeid.backup.drive.client_id');
    }

    /**
     * @return string|null
     */
    public function driveClientSecret(): ?string {
        return $this->text('chreeid.backup.drive.client_secret');
    }

    /**
     * @return string|null
     */
    public function driveRefreshToken(): ?string {
        return $this->text('chreeid.backup.drive.refresh_token');
    }

    /**
     * @return string|null
     */
    public function driveFolderId(): ?string {
        return $this->text('chreeid.backup.drive.folder_id');
    }

    /**
     * @return bool Drive へ送れるだけの設定が揃っているか
     */
    public function hasDrive(): bool {
        return $this->driveClientId() !== null
            && $this->driveClientSecret() !== null
            && $this->driveRefreshToken() !== null
            && $this->driveFolderId() !== null;
    }

    /**
     * @param string $key 設定のキー
     * @return string|null 空文字は「未設定」と同じに扱う
     */
    private function text(string $key): ?string {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
