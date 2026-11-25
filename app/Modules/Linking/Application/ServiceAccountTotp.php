<?php
namespace App\Modules\Linking\Application;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Credential\Application\EnableTotp;
use App\Modules\Credential\Application\GenerateRecoveryCodes;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Credential\Infrastructure\CredentialModel;
use App\Modules\Credential\Infrastructure\Totp;
use App\Modules\Identity\Application\UserAccounts;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * サービスが自分の画面で、利用者の TOTP を設定する。
 *
 * **データは ChreeID に置く。** サービスは画面を出すだけで、秘密鍵の保存も照合もしない。
 * 照合場所を1つにしておかないと、どちらの 2FA が効いているのか分からなくなる。
 *
 * **束ねる人格を持つアカウントは触らせない。** そこは本人が ChreeID で管理していて、
 * 許すとサービス経由で他人の 2FA を外せてしまう (ChangeServiceAccountPassword と同じ理由)。
 */
class ServiceAccountTotp {
    /**
     * 断る理由は潰さない。呼び出し元が利用者への案内を分けられるようにする
     */
    public const OK = 'ok';
    public const NOT_FOUND = 'not_found';
    public const MANAGED = 'managed';
    public const INVALID_CODE = 'invalid_code';
    public const NOT_ENABLED = 'not_enabled';

    public function __construct(
        private readonly EnableTotp $enableTotp,
        private readonly Totp $totp,
        private readonly GenerateRecoveryCodes $recoveryCodes,
        private readonly VerifyCredential $verify,
        private readonly CredentialRepository $credentials,
        private readonly UserAccounts $userAccounts,
        private readonly AuditLog $audit,
    ) {}

    /**
     * 秘密鍵を発行する。まだ有効化はしない (EnableTotp と同じく、コードの確認を挟む)。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return array{status: string, secret?: string, otpauth_uri?: string}
     */
    public function start(OAuthClientModel $client, string $serviceUserId): array {
        [$status, $link] = $this->resolve($client, $serviceUserId);
        if ($link === null) return ['status' => $status];

        $secret = $this->enableTotp->generateSecret();
        // 認証アプリにはサービスの名前で並ぶ。利用者が登録したのはそのサービスの画面なので
        $label = $link->service_email ?? $serviceUserId;

        return [
            'status' => self::OK,
            'secret' => $secret,
            'otpauth_uri' => $this->totp->provisioningUri($secret, $label, $client->name),
        ];
    }

    /**
     * 認証アプリが出したコードで確認して有効化する。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param string $secret start() で渡した秘密鍵
     * @param string $code 認証アプリが出した数字
     * @return array{status: string, recovery_codes?: list<string>} 復旧コードの平文を返せるのはこの1回だけ
     */
    public function enable(OAuthClientModel $client, string $serviceUserId, string $secret, string $code): array {
        [$status, $link] = $this->resolve($client, $serviceUserId);
        if ($link === null) return ['status' => $status];

        try {
            $codes = $this->enableTotp->execute($link->auth_identity_id, $secret, $code);
        } catch (RuntimeException) {
            return ['status' => self::INVALID_CODE];
        }

        $this->audit->record(AuditAction::CREDENTIAL_ADDED, $link->auth_identity_id, ['type' => 'totp', 'via' => $client->id]);

        return ['status' => self::OK, 'recovery_codes' => $codes];
    }

    /**
     * TOTP をやめる。復旧コードも一緒に消す (2FA が無いのに残っていても使い道が無い)。
     *
     * サービス側のセッションを乗っ取った人に外されないよう、今のコードを求める。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param string $code 認証アプリのコードか復旧コード
     * @return string self::OK か、断った理由
     */
    public function disable(OAuthClientModel $client, string $serviceUserId, string $code): string {
        [$status, $link] = $this->resolve($client, $serviceUserId);
        if ($link === null) return $status;

        $accountId = $link->auth_identity_id;
        if (!$this->credentials->requiresSecondFactor($accountId)) return self::NOT_ENABLED;
        if (!$this->verifySecondFactor($accountId, $code)) return self::INVALID_CODE;

        DB::transaction(function () use ($accountId): void {
            CredentialModel::query()
                ->where('auth_identity_id', $accountId)
                ->whereIn('type', [CredentialType::TOTP, CredentialType::RECOVERY_CODE])
                ->delete();
        });

        $this->audit->record(AuditAction::CREDENTIAL_REMOVED, $accountId, ['type' => 'totp', 'via' => $client->id]);

        return self::OK;
    }

    /**
     * 復旧コードを作り直す。古いものは使えなくなる。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @param string $code 認証アプリのコードか復旧コード
     * @return array{status: string, recovery_codes?: list<string>}
     */
    public function regenerateRecoveryCodes(OAuthClientModel $client, string $serviceUserId, string $code): array {
        [$status, $link] = $this->resolve($client, $serviceUserId);
        if ($link === null) return ['status' => $status];

        $accountId = $link->auth_identity_id;
        if (!$this->credentials->requiresSecondFactor($accountId)) return ['status' => self::NOT_ENABLED];
        if (!$this->verifySecondFactor($accountId, $code)) return ['status' => self::INVALID_CODE];

        $codes = $this->recoveryCodes->execute($accountId);
        $this->audit->record(AuditAction::RECOVERY_CODES_GENERATED, $accountId, ['via' => $client->id]);

        return ['status' => self::OK, 'recovery_codes' => $codes];
    }

    /**
     * 6桁なら TOTP、それ以外は復旧コードとして照合する。
     *
     * @param string $accountId 認証主体のID (ULID)
     * @param string $code 利用者が入力したもの
     * @return bool
     */
    private function verifySecondFactor(string $accountId, string $code): bool {
        $type = preg_match('/^\d{6}$/', $code) === 1 ? CredentialType::TOTP : CredentialType::RECOVERY_CODE;

        return $this->verify->execute($accountId, $type, ['code' => $code], new VerifiedFactors())->isSuccess();
    }

    /**
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return array{0: string, 1: ServiceAccountModel|null} 触れなければ理由と null
     */
    private function resolve(OAuthClientModel $client, string $serviceUserId): array {
        $link = ServiceAccountModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        if ($link === null) return [self::NOT_FOUND, null];
        if ($this->userAccounts->exists($link->auth_identity_id)) return [self::MANAGED, null];

        return [self::OK, $link];
    }
}
