<?php
namespace App\Modules\Backup\Http;

use App\Modules\Backup\Application\BackupCipher;
use App\Modules\Backup\Application\RunBackup;
use App\Modules\Backup\Domain\BackupSettings;
use App\Modules\Backup\Infrastructure\GoogleDrive;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * バックアップの状態と、手で流す口。
 *
 * 日次の `chreeid:backup` と同じ処理を呼ぶ。**本番では artisan を叩けない**ので、
 * cron を組めていない間もここから流せるようにしておく (掃除の画面と同じ考え方)。
 */
class AdminBackupController {
    public function __construct(
        private readonly RunBackup $backup,
        private readonly BackupCipher $cipher,
        private readonly GoogleDrive $drive,
        private readonly BackupSettings $settings,
    ) {}

    /**
     * @return Response
     */
    public function index(): Response {
        $hasKey = $this->cipher->isConfigured();
        $hasDrive = $this->drive->isConfigured();

        // 引くのは1回だけ。一覧用と理由用で別々に叩くと、通信も判定も二重になる
        $listed = $hasKey && $hasDrive ? $this->list() : ['backups' => [], 'error' => null];

        return Inertia::render('Admin/Backups/Index', [
            // 何が足りないかを分けて出す。どちらも「使えません」に潰すと直しようがない
            'hasKey' => $hasKey,
            'hasDrive' => $hasDrive,
            'keep' => $this->settings->keep(),
            'backups' => $listed['backups'],
            'listError' => $listed['error'],
        ]);
    }

    /**
     * @return RedirectResponse
     */
    public function store(): RedirectResponse {
        try {
            $result = $this->backup->execute();
        } catch (RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('backupTaken', [
            'name' => $result->name,
            'bytes' => $result->bytes,
            'tables' => $result->tables,
            'rows' => $result->rows,
            'pruned' => count($result->pruned),
        ]);
    }

    /**
     * 置いてある控えの一覧。引けなくても画面は出す。
     *
     * @return array{backups: list<array{id: string, name: string, createdTime: string}>, error: string|null}
     */
    private function list(): array {
        try {
            return ['backups' => $this->drive->list(), 'error' => null];
        } catch (RuntimeException $e) {
            // 一覧が引けないだけで画面を落とさない。理由はそのまま出す
            return ['backups' => [], 'error' => $e->getMessage()];
        }
    }
}
