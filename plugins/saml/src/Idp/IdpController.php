<?php
namespace Plugins\Saml\Idp;

use App\Modules\Plugin\Application\PluginApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * ChreeID が SAML IdP として SP にログインさせる受け口。
 *
 * ログイン、同意、サービスアカウントの選択は本体 (PluginApi::authorizeService) に任せ、
 * ここは AuthnRequest の確認と、署名した Response を返すところだけを受け持つ。
 */
class IdpController {
    /** POST で受けた要求を、GET で取り出すまで預ける時間 (秒) */
    private const STASH_TTL = 300;
    private const STASH_PREFIX = 'saml.idp.stash.';

    /** SP から来た要求の控え。本体から戻ってきたときに Response を組むのに使う */
    private const CONTEXT_PREFIX = 'saml.idp.context.';

    private readonly AuthnRequestReader $reader;
    private readonly RequestSignatureVerifier $verifier;
    private readonly ResponseBuilder $builder;
    private readonly IdpMetadata $metadata;
    private readonly PluginApi $api;

    public function __construct(AuthnRequestReader $reader, RequestSignatureVerifier $verifier, ResponseBuilder $builder, IdpMetadata $metadata, PluginApi $api) {
        $this->reader = $reader;
        $this->verifier = $verifier;
        $this->builder = $builder;
        $this->metadata = $metadata;
        $this->api = $api;
    }

    /**
     * @return HttpResponse
     */
    public function metadata(): HttpResponse {
        return response($this->metadata->xml(), 200, ['Content-Type' => 'application/samlmetadata+xml']);
    }

    /**
     * HTTP-POST で届いた要求を預け、GET へ回す。
     *
     * SP からの POST はクロスサイトなのでセッション Cookie が付かない。セッションの無いルートで受ける。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function receive(Request $request): RedirectResponse {
        $key = Str::random(40);
        Cache::put(self::STASH_PREFIX . $key, [
            'request' => $request->string('SAMLRequest')->toString(),
            'relayState' => $request->string('RelayState')->toString(),
        ], self::STASH_TTL);

        return redirect()->to(url('/plugins/saml/idp/sso') . '?' . http_build_query(['k' => $key]));
    }

    /**
     * 要求を確かめ、本体にサインインを頼む。
     *
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function sso(Request $request): RedirectResponse|Response {
        $post = $request->has('k');
        $message = $post ? Cache::pull(self::STASH_PREFIX . $request->string('k')->toString()) : [
            'request' => $request->string('SAMLRequest')->toString(),
            'relayState' => $request->string('RelayState')->toString(),
        ];
        if (!is_array($message) || $message['request'] === '') return $this->error('invalid_request');

        try {
            $authn = $this->reader->read($message['request'], !$post);
        } catch (RuntimeException) {
            return $this->error('invalid_request');
        }

        $sp = ServiceProviderModel::query()->where('entity_id', $authn->issuer)->first();
        if ($sp === null) return $this->error('unknown_sp');

        // ACS を要求から取ると、署名の無い要求で Response を別の場所へ送らせられる。登録したものだけにする
        if ($authn->acsUrl !== null && $authn->acsUrl !== $sp->acs_url) return $this->error('acs_mismatch');
        if (!$this->signatureAccepted($request, $authn, $sp, $post)) return $this->error('bad_signature');

        $context = Str::random(40);
        session()->put(self::CONTEXT_PREFIX . $context, ['sp' => $sp->id, 'requestId' => $authn->id, 'relayState' => $message['relayState']]);

        return $this->api->authorizeService($sp->client_id, $sp->scopeList(), url('/plugins/saml/idp/resume') . '?' . http_build_query(['ctx' => $context]));
    }

    /**
     * 本体でのサインインが済んだあと。署名した Response を SP へ POST させる。
     *
     * @param Request $request
     * @return Response
     */
    public function resume(Request $request): Response {
        $context = session()->pull(self::CONTEXT_PREFIX . $request->string('ctx')->toString());
        if (!is_array($context)) return $this->error('expired');

        $signIn = $this->api->takeServiceSignIn($request->string('grant')->toString());
        $sp = ServiceProviderModel::query()->find((int) $context['sp']);
        if ($signIn === null || $sp === null || $signIn->clientId !== $sp->client_id) return $this->error('expired');

        $fields = ['SAMLResponse' => $this->builder->build($signIn, $sp, $context['requestId'], $sp->acs_url)];
        if ($context['relayState'] !== '') $fields['RelayState'] = $context['relayState'];

        return Inertia::render('saml::PostBinding', ['action' => $sp->acs_url, 'fields' => $fields]);
    }

    /**
     * 証明書を登録した SP だけ、署名を必ず確かめる。登録していない SP の要求は署名を見ない。
     *
     * @param Request $request
     * @param AuthnRequest $authn
     * @param ServiceProviderModel $sp
     * @param bool $post
     * @return bool
     */
    private function signatureAccepted(Request $request, AuthnRequest $authn, ServiceProviderModel $sp, bool $post): bool {
        if ($sp->certificate === null || $sp->certificate === '') return true;
        if ($post) return $this->verifier->verifyPost($authn, $sp->certificate);

        return $this->verifier->verifyRedirect($request->server->getString('QUERY_STRING'), $sp->certificate);
    }

    /**
     * SP へエラーの Response を返すと、どの SP に返すかを要求から信じることになる。画面で伝えて止める。
     *
     * @param string $code 文言のキー (saml::Error の error.<code>)
     * @return Response
     */
    private function error(string $code): Response {
        return Inertia::render('saml::Error', ['code' => $code]);
    }
}
