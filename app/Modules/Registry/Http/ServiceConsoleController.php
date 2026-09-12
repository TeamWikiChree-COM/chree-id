<?php
namespace App\Modules\Registry\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Registry\Application\RegisterClient;
use App\Modules\Registry\Application\UpdateClient;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 第三者が自分のサービスを登録する画面。
 *
 * **ここで作れるのは未承認のサービスだけ。** 信頼状態・同意省略・サービスアカウントの
 * 発行権限は、どれも運営しか動かせない (ARCHITECTURE.md 4.2)。承認は「申請 → 審査」で、
 * 申請そのものは意思表示にすぎず、何の権限にもならない。
 *
 * 管理画面 (AdminClientController) とは扱えるものが違う。混ぜると、自分のサービスを
 * 触っているつもりで他人のサービスの設定を変えてしまう。
 */
class ServiceConsoleController {
    /**
     * 第三者に渡すスコープ。
     *
     * **選ばせない。** 欲しいだけ書ける形にすると、審査の前から広い範囲を要求できてしまう。
     * 増やしたい場合は運営が個別に足す。
     */
    private const SCOPES = 'openid profile email';

    private readonly ChreeSession $session;
    private readonly RegisterClient $register;
    private readonly UpdateClient $update;
    private readonly AuditLog $audit;

    public function __construct(
        ChreeSession $session,
        RegisterClient $register,
        UpdateClient $update,
        AuditLog $audit,
    ) {
        $this->session = $session;
        $this->register = $register;
        $this->update = $update;
        $this->audit = $audit;
    }

    /**
     * 自分が登録したサービスの一覧。
     *
     * @return Response|RedirectResponse
     */
    public function index(): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        return Inertia::render('Services/Index', [
            'services' => $this->ownedBy($accountId),
        ]);
    }

    /**
     * 登録フォーム。編集も同じ画面を使う。
     *
     * @param Request $request
     * @param string|null $client 編集するサービスの client_id
     * @return Response|RedirectResponse
     */
    public function form(Request $request, ?string $client = null): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $model = $client === null ? null : $this->ownedOrNull($accountId, $client);
        if ($client !== null && $model === null) return redirect('/services');

        return Inertia::render('Services/Form', [
            'service' => $model === null ? null : $this->describe($model),
            'scopes' => self::SCOPES,
        ]);
    }

    /**
     * 新しいサービスを登録する。
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $input = $this->validated($request);

        $registered = $this->register->execute(
            $input['name'],
            [],
            $input['redirect_uris'],
            self::SCOPES,
            // **未承認で作る。** ここを触れる形にしてはいけない
            ServiceTrust::UNAPPROVED,
            true,
            skipsConsent: false,
            canProvision: false,
            iconUrl: $input['icon_url'],
            settingsUrl: $input['settings_url'],
            ownerId: $accountId,
        );

        $this->audit->record(AuditAction::SERVICE_REGISTERED, $accountId, ['client' => $registered->client->id]);

        // 平文の secret を見せられるのはこの1回だけ
        return redirect('/services')->with('issuedSecret', [
            'clientId' => $registered->client->id,
            'secret' => $registered->secret,
        ]);
    }

    /**
     * 自分のサービスの設定を変える。
     *
     * **信頼状態には触らない。** 現在の値をそのまま渡し直す。
     *
     * @param Request $request
     * @param string $client client_id
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function update(Request $request, string $client): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $model = $this->ownedOrNull($accountId, $client);
        if ($model === null) return redirect('/services');

        $input = $this->validated($request);

        $this->update->execute(
            $model,
            $input['name'],
            $model->names ?? [],
            $input['redirect_uris'],
            $model->scopes,
            $model->trust,
            $model->skips_consent,
            $model->can_provision,
            $input['icon_url'],
            $input['settings_url'],
        );

        return redirect('/services')->with('serviceSaved', true);
    }

    /**
     * 審査を申し込む。
     *
     * @param Request $request
     * @param string $client client_id
     * @return RedirectResponse
     */
    public function requestReview(Request $request, string $client): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $model = $this->ownedOrNull($accountId, $client);
        if ($model === null) return redirect('/services');

        // 承認済みのものを申請し直させない。取り下げたいときは運営に言ってもらう
        if ($model->trust !== ServiceTrust::UNAPPROVED) return redirect('/services');

        $model->forceFill(['review_requested_at' => now()])->save();

        $this->audit->record(AuditAction::SERVICE_REVIEW_REQUESTED, $accountId, ['client' => $model->id]);

        return redirect('/services')->with('reviewRequested', true);
    }

    /**
     * @param string $accountId アカウントID (ULID)
     * @return list<array{id: string, name: string, trust: string, iconUrl: string|null, settingsUrl: string|null, redirectUris: list<string>, scopes: string, reviewRequestedAt: string|null}>
     */
    private function ownedBy(string $accountId): array {
        $result = [];
        foreach (OAuthClientModel::query()->where('owner_id', $accountId)->orderBy('name')->get() as $client) {
            $result[] = $this->describe($client);
        }

        return $result;
    }

    /**
     * **必ず持ち主で絞る。** client_id だけで引くと、他人のサービスを触れてしまう。
     *
     * @param string $accountId アカウントID (ULID)
     * @param string $client client_id
     * @return OAuthClientModel|null
     */
    private function ownedOrNull(string $accountId, string $client): ?OAuthClientModel {
        return OAuthClientModel::query()->where('owner_id', $accountId)->find($client);
    }

    /**
     * @param OAuthClientModel $client
     * @return array{id: string, name: string, trust: string, iconUrl: string|null, settingsUrl: string|null, redirectUris: list<string>, scopes: string, reviewRequestedAt: string|null}
     */
    private function describe(OAuthClientModel $client): array {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'trust' => $client->trust->value,
            'iconUrl' => $client->icon_url,
            'settingsUrl' => $client->settings_url,
            'redirectUris' => $client->redirect_uris,
            'scopes' => $client->scopes,
            'reviewRequestedAt' => $client->review_requested_at?->format('Y/m/d H:i'),
        ];
    }

    /**
     * @param Request $request
     * @return array{name: string, redirect_uris: list<string>, icon_url: string|null, settings_url: string|null}
     * @throws ValidationException
     */
    private function validated(Request $request): array {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            // 完全一致で照合するので、末尾スラッシュ違いも別物になる
            'redirect_uris.*' => ['required', 'string', 'url', 'max:500'],
            'icon_url' => ['nullable', 'string', 'url', 'max:500'],
            'settings_url' => ['nullable', 'string', 'url', 'max:500'],
        ]);

        $uris = [];
        foreach ((array) $request->input('redirect_uris', []) as $uri) {
            if (!is_string($uri)) continue;

            // 空行は足したまま送られてくる。入力のままではなく、意味のあるものだけ残す
            $trimmed = trim($uri);
            if ($trimmed !== '') $uris[] = $trimmed;
        }

        return [
            'name' => $request->string('name')->trim()->toString(),
            'redirect_uris' => $uris,
            'icon_url' => $this->trimmedOrNull($request->string('icon_url')->toString()),
            'settings_url' => $this->trimmedOrNull($request->string('settings_url')->toString()),
        ];
    }

    /**
     * @param string $value 入力された値
     * @return string|null 空欄は未設定として扱う
     */
    private function trimmedOrNull(string $value): ?string {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
