<?php
namespace App\Modules\Linking\Http;

use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\SplitException;
use App\Modules\Linking\Application\SplitServiceAccount;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
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
class SplitServiceController {
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

    public function __construct(
        private readonly ChreeSession $session,
        private readonly SplitServiceAccount $split,
    ) {}

    /**
     * @param string $serviceAccount サービスアカウントのID (ULID)
     * @return Response|RedirectResponse
     */
    public function show(string $serviceAccount): Response|RedirectResponse {
        $identityId = $this->session->accountId();
        if ($identityId === null) return redirect('/login');

        $target = $this->find($serviceAccount, $identityId);
        if ($target === null) return redirect('/')->withErrors(['split' => $this->failureMessages()[SplitException::NOT_FOUND]]);

        $options = [];
        foreach ($this->split->options($identityId) as $credential) {
            $options[] = ['id' => $credential->id, 'type' => $credential->type->value];
        }

        return Inertia::render('Settings/SplitService', [
            'serviceAccountId' => $target->id,
            'serviceName' => OAuthClientModel::query()->findOrFail($target->client_id)->name,
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
        if ($identityId === null) return redirect('/login');

        $request->validate([
            'service_account_id' => ['required', 'string'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'credentials' => ['array'],
            'credentials.*' => ['string'],
        ]);

        // 本人が持っているサービスアカウントに限る。画面から来たIDは信用しない
        $target = $this->find($request->string('service_account_id')->toString(), $identityId);
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

    /**
     * @param string $serviceAccountId サービスアカウントのID (ULID)
     * @param string $identityId 認証主体のID (ULID)
     * @return ServiceAccountModel|null 本人のものでなければ null
     */
    private function find(string $serviceAccountId, string $identityId): ?ServiceAccountModel {
        return ServiceAccountModel::query()
            ->whereKey($serviceAccountId)
            ->where('auth_identity_id', $identityId)
            ->first();
    }
}
