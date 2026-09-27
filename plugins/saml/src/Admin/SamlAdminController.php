<?php
namespace Plugins\Saml\Admin;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Plugins\Saml\Idp\IdpSettings;
use Plugins\Saml\Idp\ServiceProviderModel;
use Plugins\Saml\Idp\ServiceProviders;
use Plugins\Saml\Idp\SigningKeys;
use RuntimeException;

/**
 * SAML IdP の管理画面。署名の鍵と、SAML でつなぐサービス (SP)。
 *
 * 本番はコマンドを叩けないので、saml:idp-key と saml:sp-add と同じことを画面からできるようにしてある。
 * 中身はコマンドと同じクラスを通る。
 */
class SamlAdminController {
    /** 画面から作る証明書の有効日数。コマンドの既定と同じ */
    private const CERTIFICATE_DAYS = 3650;

    private readonly IdpSettings $settings;
    private readonly SigningKeys $keys;
    private readonly ServiceProviders $providers;

    public function __construct(IdpSettings $settings, SigningKeys $keys, ServiceProviders $providers) {
        $this->settings = $settings;
        $this->keys = $keys;
        $this->providers = $providers;
    }

    /**
     * @return Response
     */
    public function index(): Response {
        $names = $this->clientNames();

        return Inertia::render('saml::Admin/Index', [
            // key は React が予約している名前で、画面に渡らない
            'signingKey' => $this->keys->describe(),
            // 共有の flash は本体が決めた鍵しか渡さないので、ここで自分で渡す
            'keyUpdated' => (bool) session('samlKeyUpdated', false),
            'metadataUrl' => $this->settings->entityId(),
            'providers' => ServiceProviderModel::query()->orderBy('id')->get()->map(static fn (ServiceProviderModel $sp): array => [
                'id' => $sp->id,
                'clientId' => $sp->client_id,
                'clientName' => $names[$sp->client_id] ?? $sp->client_id,
                'entityId' => $sp->entity_id,
                'acsUrl' => $sp->acs_url,
                'signedRequests' => $sp->certificate !== null,
            ])->values()->all(),
        ]);
    }

    /**
     * 鍵を作る。既にあるときは、作り直しを明示したときだけ。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function generateKey(Request $request): RedirectResponse {
        if ($this->settings->isConfigured() && !$request->boolean('replace')) return back();

        try {
            $this->keys->generate(self::CERTIFICATE_DAYS);
        } catch (RuntimeException $e) {
            // openssl が使えないサーバがある。手元で作った鍵を取り込む道を画面に出す
            Log::warning('SAML signing key generation failed', ['exception' => $e]);

            return back()->withErrors(['key' => 'key_generate_failed']);
        }

        return back()->with('samlKeyUpdated', true);
    }

    /**
     * 手元で作った鍵と証明書を取り込む。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function importKey(Request $request): RedirectResponse {
        if ($this->settings->isConfigured() && !$request->boolean('replace')) return back();

        $imported = $this->keys->import($request->string('private_key')->toString(), $request->string('certificate')->toString());
        if (!$imported) return back()->withErrors(['import' => 'key_pair_invalid']);

        return back()->with('samlKeyUpdated', true);
    }

    /**
     * @return Response
     */
    public function create(): Response {
        return $this->form(null);
    }

    /**
     * @param ServiceProviderModel $provider
     * @return Response
     */
    public function edit(ServiceProviderModel $provider): Response {
        return $this->form($provider);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse {
        return $this->save($request, null);
    }

    /**
     * @param Request $request
     * @param ServiceProviderModel $provider
     * @return RedirectResponse
     */
    public function update(Request $request, ServiceProviderModel $provider): RedirectResponse {
        return $this->save($request, $provider);
    }

    /**
     * SAML の設定だけを外す。サービスそのもの (oauth_clients) とサービスアカウントは残す。
     *
     * @param ServiceProviderModel $provider
     * @return RedirectResponse
     */
    public function destroy(ServiceProviderModel $provider): RedirectResponse {
        $provider->delete();

        return redirect('/plugins/saml/admin');
    }

    /**
     * @param Request $request
     * @param ServiceProviderModel|null $current
     * @return RedirectResponse
     */
    private function save(Request $request, ?ServiceProviderModel $current): RedirectResponse {
        $input = [
            'client_id' => $request->string('client_id')->trim()->toString(),
            'entity_id' => $request->string('entity_id')->trim()->toString(),
            'acs_url' => $request->string('acs_url')->trim()->toString(),
            'certificate' => $request->string('certificate')->trim()->toString(),
            'scopes' => $request->string('scopes')->toString(),
        ];

        $errors = $this->providers->check($input, $current);
        if ($errors !== []) return back()->withErrors($errors)->withInput();

        $this->providers->save($input, $current);

        return redirect('/plugins/saml/admin');
    }

    /**
     * @param ServiceProviderModel|null $provider
     * @return Response
     */
    private function form(?ServiceProviderModel $provider): Response {
        $names = $this->clientNames();

        return Inertia::render('saml::Admin/Form', [
            'provider' => $provider === null ? null : [
                'id' => $provider->id,
                'clientId' => $provider->client_id,
                'entityId' => $provider->entity_id,
                'acsUrl' => $provider->acs_url,
                'certificate' => $provider->certificate ?? '',
                'scopes' => $provider->scopes,
            ],
            'clients' => array_map(static fn (string $id, string $name): array => ['id' => $id, 'name' => $name], array_keys($names), $names),
        ]);
    }

    /**
     * @return array<string, string> client_id => 表示名
     */
    private function clientNames(): array {
        $names = [];
        foreach (OAuthClientModel::query()->orderBy('name')->get() as $client) $names[$client->id] = $client->displayName();

        return $names;
    }
}
