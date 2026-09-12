<?php
namespace App\Modules\Registry\Http;

use App\Modules\Identity\Infrastructure\UserAccountModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Registry\Application\RegisterClient;
use App\Modules\Registry\Application\RotateClientSecret;
use App\Modules\Registry\Application\UpdateClient;
use App\Modules\Registry\Domain\ServiceTrust;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 接続サービス (OAuth クライアント) の管理画面。
 *
 * 本番サーバでコマンドを叩きにくいので、登録と修正を画面から行えるようにしてある。
 * 中身は Application 側と共有していて、コンソールコマンドと同じ経路を通る。
 */
class AdminClientController {
    public function __construct(
        private readonly RegisterClient $register,
        private readonly UpdateClient $update,
        private readonly RotateClientSecret $rotate,
    ) {}

    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response {
        return Inertia::render('Admin/Clients/Index', [
            'clients' => $this->all(),
            // 登録・再発行の直後だけ平文を渡す。次の表示では消える
            'issued' => $request->session()->get('issuedSecret'),
        ]);
    }

    /**
     * @return Response
     */
    public function create(): Response {
        return Inertia::render('Admin/Clients/Form', [
            'client' => null,
            'trustOptions' => $this->trustOptions(),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse {
        $input = $this->validated($request);

        $registered = $this->register->execute(
            $input['name'],
            $input['names'],
            $input['redirect_uris'],
            $input['scopes'],
            $input['trust'],
            $request->boolean('is_confidential', true),
            $input['skips_consent'],
            $input['can_provision'],
            $input['icon_url'],
        );

        return redirect('/admin/clients')->with('issuedSecret', [
            'clientId' => $registered->client->id,
            'secret' => $registered->secret,
        ]);
    }

    /**
     * @param string $client client_id
     * @return Response
     */
    public function edit(string $client): Response {
        $model = OAuthClientModel::query()->findOrFail($client);

        return Inertia::render('Admin/Clients/Form', [
            'client' => $this->toArray($model),
            'trustOptions' => $this->trustOptions(),
        ]);
    }

    /**
     * @param Request $request
     * @param string $client client_id
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function updateClient(Request $request, string $client): RedirectResponse {
        $model = OAuthClientModel::query()->findOrFail($client);
        $input = $this->validated($request);

        $this->update->execute(
            $model,
            $input['name'],
            $input['names'],
            $input['redirect_uris'],
            $input['scopes'],
            $input['trust'],
            $input['skips_consent'],
            $input['can_provision'],
            $input['icon_url'],
        );

        return redirect('/admin/clients');
    }

    /**
     * @param string $client client_id
     * @return RedirectResponse
     */
    public function rotateSecret(string $client): RedirectResponse {
        $model = OAuthClientModel::query()->findOrFail($client);

        if (!$model->is_confidential) return redirect('/admin/clients');

        return redirect('/admin/clients')->with('issuedSecret', [
            'clientId' => $model->id,
            'secret' => $this->rotate->execute($model),
        ]);
    }

    /**
     * @param string $client client_id
     * @return RedirectResponse
     */
    public function destroy(string $client): RedirectResponse {
        OAuthClientModel::query()->findOrFail($client)->delete();

        return redirect('/admin/clients');
    }

    /**
     * @param Request $request
     * @return array{name: string, names: array<string, string>, redirect_uris: list<string>, scopes: string, trust: ServiceTrust, skips_consent: bool, can_provision: bool, icon_url: string|null}
     * @throws ValidationException
     */
    private function validated(Request $request): array {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // 言語ごとの表示名。空欄なら name を出すので、埋めさせない
            'names' => ['array'],
            'names.*' => ['nullable', 'string', 'max:100'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            // 完全一致で照合するので、末尾スラッシュ違いも別物になる
            'redirect_uris.*' => ['required', 'string', 'url', 'max:500'],
            'scopes' => ['required', 'string', 'max:255'],
            'trust' => ['required', 'string'],
            'skips_consent' => ['boolean'],
            'can_provision' => ['boolean'],
            // 画像そのものは持たない。置き場所はサービス側に任せる
            'icon_url' => ['nullable', 'string', 'url', 'max:500'],
        ]);

        $trust = ServiceTrust::tryFrom($request->string('trust')->toString());
        if ($trust === null) {
            throw ValidationException::withMessages(['trust' => __('admin.client.invalid_trust')]);
        }

        $uris = [];
        foreach ($request->collect('redirect_uris') as $uri) {
            if (!is_string($uri)) continue;

            $uri = trim($uri);
            if ($uri !== '') $uris[] = $uri;
        }

        return [
            'name' => $request->string('name')->toString(),
            'names' => $this->localeNames($request),
            'redirect_uris' => $uris,
            'scopes' => $request->string('scopes')->toString(),
            'trust' => $trust,
            // 同意の省略も発行権限も、信頼状態とは別の設定
            'skips_consent' => $request->boolean('skips_consent'),
            'can_provision' => $request->boolean('can_provision'),
            'icon_url' => $this->trimmedOrNull($request->string('icon_url')->toString()),
        ];
    }

    /**
     * @return list<array{id: string, name: string, redirectUris: list<string>, scopes: string, isConfidential: bool, trust: string, skipsConsent: bool, canProvision: bool, iconUrl: string|null, serviceAccounts: int, migratedAccounts: int, createdAt: string|null}>
     */
    private function all(): array {
        $result = [];
        foreach (OAuthClientModel::query()->orderBy('name')->get() as $client) {
            $result[] = $this->toArray($client);
        }

        return $result;
    }

    /**
     * @param OAuthClientModel $client
     * @return array{id: string, name: string, names: array<string, string>, redirectUris: list<string>, scopes: string, isConfidential: bool, trust: string, skipsConsent: bool, canProvision: bool, iconUrl: string|null, serviceAccounts: int, migratedAccounts: int, createdAt: string|null}
     */
    private function toArray(OAuthClientModel $client): array {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'names' => $client->names ?? [],
            'redirectUris' => $client->redirect_uris,
            'scopes' => $client->scopes,
            'isConfidential' => $client->is_confidential,
            'trust' => $client->trust->value,
            'skipsConsent' => $client->skips_consent,
            'canProvision' => $client->can_provision,
            'iconUrl' => $client->icon_url,
            // 移行元へ落とす経路をいつ消せるかの目安になる
            'serviceAccounts' => $this->countServiceAccounts($client->id),
            'migratedAccounts' => $this->countMigrated($client->id),
            'createdAt' => $client->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @param string $clientId サービスの client_id
     * @return int このサービスのサービスアカウント数
     */
    private function countServiceAccounts(string $clientId): int {
        return ServiceAccountModel::query()->where('client_id', $clientId)->count();
    }

    /**
     * 束ねる人格を持つに至った数。移行がどこまで進んでいるかの目安。
     *
     * @param string $clientId サービスの client_id
     * @return int
     */
    private function countMigrated(string $clientId): int {
        return ServiceAccountModel::query()
            ->where('client_id', $clientId)
            ->whereIn('auth_identity_id', UserAccountModel::query()->select('auth_identity_id'))
            ->count();
    }

    /**
     * @param string $value 入力値
     * @return string|null 空なら null
     */
    private function trimmedOrNull(string $value): ?string {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function trustOptions(): array {
        return [
            ['value' => ServiceTrust::OFFICIAL->value, 'label' => __('admin.client.trust.official')],
            ['value' => ServiceTrust::APPROVED->value, 'label' => __('admin.client.trust.approved')],
            ['value' => ServiceTrust::UNAPPROVED->value, 'label' => __('admin.client.trust.unapproved')],
            ['value' => ServiceTrust::DISABLED->value, 'label' => __('admin.client.trust.disabled')],
        ];
    }

    /**
     * 言語ごとの表示名を整える。
     *
     * 空欄は持たない。**「空文字が入っている」と「入れていない」を区別しない**ため。
     * 区別すると、消したつもりの欄が空の名前として出てしまう。
     *
     * @param Request $request 送られてきた入力
     * @return array<string, string>
     */
    private function localeNames(Request $request): array {
        /** @var array<string, string> $input */
        $input = $request->array('names');
        $names = [];

        /** @var list<string> $locales */
        $locales = config('chreeid.locales');

        foreach ($locales as $locale) {
            $name = trim((string) ($input[$locale] ?? ''));

            if ($name !== '') $names[$locale] = $name;
        }

        return $names;
    }
}
