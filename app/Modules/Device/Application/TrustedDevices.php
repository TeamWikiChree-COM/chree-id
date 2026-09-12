<?php
namespace App\Modules\Device\Application;

use App\Modules\Device\Domain\DeviceLabel;
use App\Modules\Device\Infrastructure\TrustedDeviceModel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 2段階目を省略してよい端末の管理。
 *
 * 端末が持つのは平文トークン、こちらが持つのはそのハッシュだけ。
 * 表を読めても、そこから省略に使えるトークンは作れない。
 */
class TrustedDevices {
    /** 端末に配るクッキーの名前 */
    public const COOKIE = 'chreeid_trusted_device';

    /** 信頼を保つ日数。過ぎたら次のログインで2段階目を求める */
    public const LIFETIME_DAYS = 30;

    /**
     * この端末を信頼する。
     *
     * @param Request $request
     * @param string $accountId アカウントID (ULID)
     * @return string 端末に配る平文トークン
     */
    public function remember(Request $request, string $accountId): string {
        $token = Str::random(64);

        TrustedDeviceModel::create([
            'auth_identity_id' => $accountId,
            'token_hash' => $this->hash($token),
            'label' => DeviceLabel::from($request->userAgent()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        return $token;
    }

    /**
     * 2段階目を省略してよい端末か。
     *
     * 通ったときは最終利用を進めるが、期限は延ばさない。延ばすと、一度信頼した端末が
     * 使い続けるかぎり永久に2段階目を求められなくなる。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string|null $token クッキーの平文トークン
     * @return bool
     */
    public function isTrusted(string $accountId, ?string $token): bool {
        $device = $this->find($accountId, $token);
        if ($device === null) return false;

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    /**
     * 一覧に出す形で返す。期限切れは含めない。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string|null $token いま使っている端末のトークン。印を付けるために使う
     * @return list<array{id: string, label: string, ipAddress: string|null, lastUsedAt: string|null, expiresAt: string, isCurrent: bool}>
     */
    public function listFor(string $accountId, ?string $token): array {
        $currentHash = $token === null ? null : $this->hash($token);

        $rows = TrustedDeviceModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => $row->id,
                'label' => $row->label ?? DeviceLabel::from($row->user_agent),
                'ipAddress' => $row->ip_address,
                'lastUsedAt' => $row->last_used_at?->toDateTimeString(),
                'expiresAt' => $row->expires_at->toDateTimeString(),
                'isCurrent' => $currentHash !== null && hash_equals($row->token_hash, $currentHash),
            ];
        }

        return $result;
    }

    /**
     * 信頼を取り消す。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $deviceId 取り消す端末のID
     * @return bool 実際に取り消したか
     */
    public function revoke(string $accountId, string $deviceId): bool {
        $deleted = TrustedDeviceModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('id', $deviceId)
            ->delete();

        return is_int($deleted) && $deleted > 0;
    }

    /**
     * すべての端末の信頼を取り消す。
     *
     * @param string $accountId アカウントID (ULID)
     * @return int 取り消した台数
     */
    public function revokeAll(string $accountId): int {
        $deleted = TrustedDeviceModel::query()->where('auth_identity_id', $accountId)->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * 期限切れを消す。
     *
     * @return int 消した件数
     */
    public function prune(): int {
        $deleted = TrustedDeviceModel::query()->where('expires_at', '<=', now())->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * リクエストが持ってきた信頼トークンを取り出す。
     *
     * クッキーは配列で来ることもあるので、文字列以外は持っていないものとして扱う。
     *
     * @param Request $request
     * @return string|null 持っていなければ null
     */
    public function tokenFrom(Request $request): ?string {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) ? $token : null;
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @param string|null $token クッキーの平文トークン
     * @return TrustedDeviceModel|null 見つからない、または期限切れなら null
     */
    private function find(string $accountId, ?string $token): ?TrustedDeviceModel {
        if ($token === null || $token === '') return null;

        return TrustedDeviceModel::query()
            ->where('auth_identity_id', $accountId)
            ->where('token_hash', $this->hash($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param string $token 平文トークン
     * @return string SHA-256 の16進表現
     */
    private function hash(string $token): string {
        return hash('sha256', $token);
    }
}
