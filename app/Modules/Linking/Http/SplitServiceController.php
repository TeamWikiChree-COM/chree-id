<?php
namespace App\Modules\Linking\Http;

use App\Http\Controllers\Controller;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Identity\Application\ChreeSession;
use App\Modules\Linking\Application\SplitException;
use App\Modules\Linking\Application\SplitServiceAccount;
use App\Modules\Client\Application\ClientNames;
use App\Support\Http\LoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * サービスアカウントを、まとめから外して単独に戻す画面。統合の逆操作。
 *
 * 「ChreeID にまとめるのはやめたいが、そのサービスは使い続けたい」ための入口。
 * サービスから見た識別子 (`sub`) は変わらないので、向こうは何も気付かない。
 */
class SplitServiceController extends Controller {
    /**
     * 理由ごとの表示。内部の理由コードはそのまま出さない
     *
     * @return array<string, string>
     */
    private function failureMessages(): array {
        return [
            SplitException::NO_CREDENTIAL => __('linking.split.no_credential'),
            SplitException::EMAIL_TAKEN => __('linking.split.email_taken'),
            SplitException::NOT_FOUND => __('linking.split.not_found'),
        ];
    }

    private readonly ChreeSession $session;
    private readonly SplitServiceAccount $split;
    private readonly ClientNames $clientNames;

    public function __construct(ChreeSession $session, SplitServiceAccount $split, ClientNames $clientNames) {
        $this->session = $session;
        $this->split = $split;
        $this->clientNames = $clientNames;
    }

    /**
     * @param string $serviceAccount サービスアカウントのID (ULID)
     * @return Response|RedirectResponse
     */
    public function show(string $serviceAccount): Response|RedirectResponse {
        $identityId = $this->session->accountId();
        if ($identityId === null) return LoginRedirect::guest();

        $target = $this->split->target($serviceAccount, $identityId);
        if ($target === null) return redirect('/')->withErrors(['split' => $this->failureMessages()[SplitException::NOT_FOUND]]);

        $options = [];
        foreach ($this->split->options($identityId) as $credential) {
            $data = is_array($credential->data) ? $credential->data : [];
            // 同じ種別が並んだとき (Google を2つ連携しているなど) に見分けが付くように
            $detail = $data['email'] ?? null;
            // 外部ログインはどこの連携かを識別子の頭 (google:… など) で持っている
            $provider = $credential->type === CredentialType::OAUTH ? explode(':', (string) $credential->identifier)[0] : null;
            $options[] = [
                'id' => $credential->id,
                'type' => $credential->type->value,
                'provider' => $provider,
                'detail' => is_string($detail) ? $detail : null,
            ];
        }

        return Inertia::render('Settings/SplitService', [
            'serviceAccountId' => $target->id,
            'serviceName' => $this->clientNames->of($target->client_id),
            'serviceUserId' => $target->service_user_id,
            'options' => $options,
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse {
        $identityId = $this->session->accountId();
        if ($identityId === null) return LoginRedirect::guest();

        $request->validate([
            'service_account_id' => ['required', 'string'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'credentials' => ['array'],
            'credentials.*' => ['string'],
        ]);

        // 本人が持っているサービスアカウントに限る。画面から来たIDは信用しない
        $target = $this->split->target($request->string('service_account_id')->toString(), $identityId);
        if ($target === null) return redirect('/')->withErrors(['split' => $this->failureMessages()[SplitException::NOT_FOUND]]);

        /** @var list<string> $chosen */
        $chosen = $request->input('credentials', []);

        $email = $request->string('email')->trim()->toString();
        $displayName = $request->string('display_name')->trim()->toString();

        try {
            $this->split->execute($target, $email, $displayName === '' ? null : $displayName, $chosen);
        } catch (SplitException $e) {
            throw ValidationException::withMessages([
                $e->reason === SplitException::EMAIL_TAKEN ? 'email' : 'credentials' => $this->failureMessages()[$e->reason],
            ]);
        }

        return redirect('/')->with('serviceSplit', true);
    }
}
