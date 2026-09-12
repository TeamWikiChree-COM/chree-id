<?php
namespace App\Modules\Credential\Http;

use App\Modules\Credential\Application\CompletePasskeyRegistration;
use App\Modules\Device\Domain\DeviceLabel;
use App\Modules\Credential\Application\StartPasskeyRegistration;
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
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function options(Request $request): JsonResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return response()->json(['error' => 'unauthenticated'], 401);

        $account = $this->accounts->findById($accountId);
        if ($account === null) return response()->json(['error' => 'unauthenticated'], 401);

        $options = $this->start->execute($account);
        $request->session()->put(self::PENDING_OPTIONS, serialize($options));

        return response()->json(json_decode($this->serializer->encodeOptions($options), true));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return response()->json(['error' => 'unauthenticated'], 401);

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

        return response()->json(['ok' => true]);
    }
}
