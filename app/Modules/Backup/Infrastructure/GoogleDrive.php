<?php
namespace App\Modules\Backup\Infrastructure;

use App\Modules\Backup\Domain\BackupSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Drive への置き場。
 *
 * refresh token で毎回アクセストークンを取り直す。サービスアカウントを使わないのは、
 * **サービスアカウントは自分の容量を持たない**ため。共有ドライブを用意しない限り
 * 置けず、個人の Drive へ置きたい用途に合わない (ModParks と同じ判断)。
 */
class GoogleDrive {
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';
    private const FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    /** 送受信の待ち時間。バックアップは大きくなりうるので短くしすぎない */
    private const TIMEOUT_SECONDS = 120;

    public function __construct(private readonly BackupSettings $settings) {}

    /**
     * @return bool 4つ揃っているか
     */
    public function isConfigured(): bool {
        return $this->settings->hasDrive();
    }

    /**
     * @param string $name 置くファイル名
     * @param string $content 中身 (暗号化済み)
     * @return string 置いたファイルのID
     * @throws RuntimeException 置けなかった
     */
    public function upload(string $name, string $content): string {
        $metadata = json_encode([
            'name' => $name,
            'parents' => [$this->folderId()],
        ], JSON_THROW_ON_ERROR);

        $response = Http::withToken($this->accessToken())
            ->timeout(self::TIMEOUT_SECONDS)
            ->attach('metadata', $metadata, 'metadata.json', ['Content-Type' => 'application/json'])
            ->attach('file', $content, $name, ['Content-Type' => 'application/octet-stream'])
            ->post(self::UPLOAD_URL . '?uploadType=multipart&fields=id');

        if ($response->failed()) {
            throw new RuntimeException('Drive に置けませんでした: ' . $response->body());
        }

        $id = $response->json('id');
        if (!is_string($id)) throw new RuntimeException('Drive がファイルIDを返しませんでした');

        return $id;
    }

    /**
     * 置いてあるものを新しい順に返す。
     *
     * @return list<array{id: string, name: string, createdTime: string}>
     * @throws RuntimeException 一覧を引けなかった
     */
    public function list(): array {
        $response = Http::withToken($this->accessToken())
            ->timeout(self::TIMEOUT_SECONDS)
            ->get(self::FILES_URL, [
                'q' => sprintf("'%s' in parents and trashed = false", $this->folderId()),
                'orderBy' => 'createdTime desc',
                'fields' => 'files(id,name,createdTime)',
                'pageSize' => 100,
            ]);

        if ($response->failed()) throw new RuntimeException('Drive の一覧を引けませんでした: ' . $response->body());

        /** @var list<array{id: string, name: string, createdTime: string}> $files */
        $files = $response->json('files') ?? [];

        return $files;
    }

    /**
     * @param string $id 消すファイルのID
     * @return void
     * @throws RuntimeException 消せなかった
     */
    public function delete(string $id): void {
        $response = Http::withToken($this->accessToken())
            ->timeout(self::TIMEOUT_SECONDS)
            ->delete(self::FILES_URL . '/' . $id);

        if ($response->failed()) throw new RuntimeException('Drive から消せませんでした: ' . $response->body());
    }

    /**
     * @return string アクセストークン
     * @throws RuntimeException 取り直せなかった
     */
    private function accessToken(): string {
        $response = Http::asForm()->timeout(self::TIMEOUT_SECONDS)->post(self::TOKEN_URL, [
            'client_id' => $this->settings->driveClientId() ?? '',
            'client_secret' => $this->settings->driveClientSecret() ?? '',
            'refresh_token' => $this->settings->driveRefreshToken() ?? '',
            'grant_type' => 'refresh_token',
        ]);

        $token = $response->json('access_token');

        // 本文の error を見る。交換に失敗しても 200 が返ることがある (GitHub で踏んだ)
        if ($response->failed() || !is_string($token)) {
            throw new RuntimeException('Drive のアクセストークンを取れませんでした: ' . $response->body());
        }

        return $token;
    }

    /**
     * @return string 置き先のフォルダID
     * @throws RuntimeException 設定が足りない
     */
    private function folderId(): string {
        $id = $this->settings->driveFolderId();
        if ($id === null) throw new RuntimeException('Google Drive のフォルダIDが設定されていません');

        return $id;
    }
}
