<?php
namespace App\Modules\Credential\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Credential\Application\CompletePasskeyRegistration;
use App\Modules\Device\Domain\DeviceLabel;
use App\Modules\Credential\Application\StartPasskeyRegistration;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyContext;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyDiagnostics;
use App\Modules\Credential\Infrastructure\Passkey\PasskeySerializer;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * パスキーの登録 (ブラウザとやり取りするので JSON で返す)
 */
class PasskeyController {
    /** 応答の検証に、発行時と同じ options を使う。使い回すとリプレイを許す */
    private const PENDING_OPTIONS = 'passkey.creation_options';

    public function __construct(
        private readonly ChreeSession $session,
        private readonly AuthIdentityRepository $accounts,
        private readonly StartPasskeyRegistration $start,
        private readonly CompletePasskeyRegistration $complete,
        private readonly PasskeySerializer $serializer,
        private readonly AuditLog $audit,
        private readonly PasskeyContext $context,
        private readonly PasskeyDiagnostics $diagnostics,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function options(Request $request): JsonResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return $this->signedOut('options', $request);

        $account = $this->accounts->findById($accountId);
        if ($account === null) return $this->signedOut('options', $request);

        // RP ID がホストとずれていると、ブラウザは options を受け取った時点で必ず断る。
        // 何も言わずに渡すと「登録できませんでした」としか出ず、原因が設定だと分からない
        if (!$this->context->matchesHost($request->getHost())) {
            return response()->json([
                'error' => 'rp_id_mismatch',
                'reason' => __('passkey.rp_id_mismatch', [
                    'host' => $request->getHost(),
                    'rp' => $this->context->rpId(),
                ]),
            ], 422);
        }

        $options = $this->start->execute($account);
        $request->session()->put(self::PENDING_OPTIONS, serialize($options));

        // 次の往復と突き合わせるために、成功した側も残す
        $this->diagnostics->reportStep('options', $request);

        return response()->json(json_decode($this->serializer->encodeOptions($options), true));
    }

    /**
     * セッションが見つからないときの応答。
     *
     * **ここを素の 401 にしない。** 画面には「登録できませんでした (401)」としか出ず、
     * クッキーが届いていないのかセッションが消えたのかを区別できない。
     *
     * @param string $step どの往復で起きたか
     * @param Request $request 来ている要求
     * @return JsonResponse
     */
    private function signedOut(string $step, Request $request): JsonResponse {
        $this->diagnostics->reportMissingSession($step, $request);

        return response()->json([
            'error' => 'unauthenticated',
            'reason' => __('passkey.signed_out'),
        ], 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse {
        $accountId = $this->session->accountId();
        // if ($accountId === null) return $this->signedOut('register', $request);

        $stored = $request->session()->pull(self::PENDING_OPTIONS);
        if (!is_string($stored)) return response()->json(['error' => 'no_challenge'], 400);

        $options = unserialize($stored, ['allowed_classes' => true]);
        if (!$options instanceof PublicKeyCredentialCreationOptions) {
            return response()->json(['error' => 'no_challenge'], 400);
        }

        $label = $request->string('label')->toString();

        try {
            $this->complete->execute(
                $accountId,
                $options,
                $request->string('credential')->toString(),
                $label === '' ? DeviceLabel::from($request->userAgent()) : $label,
            );
        } catch (RuntimeException $e) {
            // 原因を捨てると「パスキーを登録できませんでした」しか残らず、
            // rpId のずれなのか応答の壊れなのかが分からなくなる。
            // 中身 (ライブラリ側の理由) はログにだけ残し、画面には概要を返す
            report($e);

            return response()->json(['error' => 'invalid_credential', 'reason' => $e->getMessage()], 422);
        }

        $this->audit->record(AuditAction::CREDENTIAL_ADDED, $accountId, ['type' => 'passkey']);

        return response()->json(['ok' => true]);
    }
}
