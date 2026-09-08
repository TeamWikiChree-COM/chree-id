<?php
namespace App\Modules\Registry\Http;

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
            $input['redirect_uris'],
            $input['scopes'],
            $input['trust'],
            $request->boolean('is_confidential', true),
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
            $input['redirect_uris'],
            $input['scopes'],
            $input['trust'],
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
     * @return array{name: string, redirect_uris: list<string>, scopes: string, trust: ServiceTrust}
     * @throws ValidationException
     */
    private function validated(Request $request): array {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'redirect_uris' => ['required', 'array', 'min:1'],
            // 完全一致で照合するので、末尾スラッシュ違いも別物になる
            'redirect_uris.*' => ['required', 'string', 'url', 'max:500'],
            'scopes' => ['required', 'string', 'max:255'],
            'trust' => ['required', 'string'],
        ]);

        $trust = ServiceTrust::tryFrom($request->string('trust')->toString());
        if ($trust === null) {
            throw ValidationException::withMessages(['trust' => '信頼状態の指定が不正です']);
        }

        $uris = [];
        foreach ($request->collect('redirect_uris') as $uri) {
            if (!is_string($uri)) continue;

            $uri = trim($uri);
            if ($uri !== '') $uris[] = $uri;
        }

        return [
            'name' => $request->string('name')->toString(),
            'redirect_uris' => $uris,
            'scopes' => $request->string('scopes')->toString(),
            'trust' => $trust,
        ];
    }

    /**
     * @return list<array{id: string, name: string, redirectUris: list<string>, scopes: string, isConfidential: bool, trust: string, createdAt: string|null}>
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
     * @return array{id: string, name: string, redirectUris: list<string>, scopes: string, isConfidential: bool, trust: string, createdAt: string|null}
     */
    private function toArray(OAuthClientModel $client): array {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'redirectUris' => $client->redirect_uris,
            'scopes' => $client->scopes,
            'isConfidential' => $client->is_confidential,
            'trust' => $client->trust->value,
            'createdAt' => $client->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function trustOptions(): array {
        return [
            ['value' => ServiceTrust::OFFICIAL->value, 'label' => '公式 (同意画面を省略)'],
            ['value' => ServiceTrust::APPROVED->value, 'label' => '承認済み'],
            ['value' => ServiceTrust::UNAPPROVED->value, 'label' => '未承認'],
            ['value' => ServiceTrust::DISABLED->value, 'label' => '停止中 (ログインさせない)'],
        ];
    }
}
