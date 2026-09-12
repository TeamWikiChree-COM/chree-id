<?php
namespace App\Modules\Linking\Http;

use App\Modules\Credential\Application\CompletePasskeyRegistration;
use App\Modules\Device\Domain\DeviceLabel;
use App\Modules\Credential\Application\StartPasskeyRegistration;
use App\Modules\Credential\Infrastructure\Passkey\PasskeyChallenges;
use App\Modules\Credential\Infrastructure\Passkey\PasskeySerializer;
use App\Modules\Identity\Domain\AuthIdentityRepository;
use App\Modules\Linking\Application\ClaimTickets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 引き取り画面からのパスキー登録。
 *
 * ログイン前なので、セッションの利用者ではなく引き取りトークンで
 * 対象アカウントを特定する。トークン自体の要求元・有効期限は
 * ClaimTickets が保証する。
 */
class ClaimPasskeyController {
    /** 応答の検証に、発行時と同じ options を使う。使い回すとリプレイを許す */
    private const PENDING_OPTIONS = 'claim.passkey.challenge_handle';

    public function __construct(
        private readonly ClaimTickets $tickets,
        private readonly AuthIdentityRepository $accounts,
        private readonly StartPasskeyRegistration $start,
        private readonly CompletePasskeyRegistration $complete,
        private readonly PasskeySerializer $serializer,
        private readonly PasskeyChallenges $challenges,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function options(Request $request): JsonResponse {
        $link = $this->tickets->find($request->string('token')->toString());
        if ($link === null || $link->isClaimed()) return response()->json(['error' => 'invalid_ticket'], 404);

        $account = $this->accounts->findById($link->auth_identity_id);
        if ($account === null) return response()->json(['error' => 'invalid_ticket'], 404);

        $options = $this->start->execute($account);
        // **セッションには引換券だけ** (PasskeyChallenges の注意書きを参照)
        $request->session()->put(self::PENDING_OPTIONS, $this->challenges->remember($options));

        return response()->json(json_decode($this->serializer->encodeOptions($options), true));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse {
        $link = $this->tickets->find($request->string('token')->toString());
        if ($link === null || $link->isClaimed()) return response()->json(['error' => 'invalid_ticket'], 404);

        $options = $this->challenges->pull($request->session()->pull(self::PENDING_OPTIONS));
        if ($options === null) return response()->json(['error' => 'no_challenge'], 400);

        $label = $request->string('label')->toString();

        try {
            $this->complete->execute(
                $link->auth_identity_id,
                $options,
                $request->string('credential')->toString(),
                $label === '' ? DeviceLabel::from($request->userAgent()) : $label,
            );
        } catch (RuntimeException) {
            return response()->json(['error' => 'invalid_credential'], 422);
        }

        return response()->json(['ok' => true]);
    }
}
