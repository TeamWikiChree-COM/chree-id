<?php
namespace App\Modules\Audit\Http;

use App\Modules\Audit\Application\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 運営が見る記録。
 *
 * 全アカウント分が並ぶ。入口は EnsureAdmin の内側にしか置かないこと。
 */
class AdminAuditController {
    /** 一覧に出す件数。絞り込みはまだ無いので、直近だけ出す */
    private const LIMIT = 200;

    private readonly AuditLog $audit;

    public function __construct(AuditLog $audit) {
        $this->audit = $audit;
    }

    /**
     * @return Response
     */
    public function index(): Response {
        return Inertia::render('Admin/Audit/Index', [
            'events' => $this->audit->recent(self::LIMIT),
            'keepDays' => AuditLog::KEEP_DAYS,
        ]);
    }
}
