<?php
namespace Plugins\Saml\Sp;

use App\Modules\ExternalLogin\Domain\ExternalIdentity;
use App\Modules\Plugin\Application\PluginApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SAML の受け口 (ChreeID が SP 側)。
 */
class SamlController {
    /** POST で受けた応答を、GET で取り出すまで預ける時間 (秒) */
    private const STASH_TTL = 300;

    private const STASH_PREFIX = 'saml.stash.';

    private readonly SamlSettings $settings;
    private readonly SamlResponseReader $reader;
    private readonly PluginApi $api;

    public function __construct(SamlSettings $settings, SamlResponseReader $reader, PluginApi $api) {
        $this->settings = $settings;
        $this->reader = $reader;
        $this->api = $api;
    }

    /**
     * IdP に登録してもらう SP のメタデータ。
     *
     * @return Response
     */
    public function metadata(): Response {
        $xml = $this->settings->buildSpOnly()->getSPMetadata();

        return response($xml, 200, ['Content-Type' => 'application/samlmetadata+xml']);
    }

    /**
     * IdP からの POST を預け、同じ URL の GET へ回す。
     *
     * ここはセッションの無いルート。セッションを見るのは land() から。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function receive(Request $request): RedirectResponse {
        $key = Str::random(40);
        Cache::put(self::STASH_PREFIX . $key, [
            'response' => $request->string('SAMLResponse')->toString(),
            'relayState' => $request->string('RelayState')->toString(),
        ], self::STASH_TTL);

        // 同じパスにしておくのは、php-saml が Response の Destination を「今の URL (クエリ抜き)」と照らすため
        return redirect()->to(url('/plugins/saml/acs') . '?' . http_build_query(['k' => $key]));
    }

    /**
     * 預けた応答を取り出し、本体のログイン処理へ渡す。
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function land(Request $request): RedirectResponse {
        $stash = Cache::pull(self::STASH_PREFIX . $request->string('k')->toString());
        if (!is_array($stash) || $stash['response'] === '') return $this->api->abortExternalLogin(__('auth.external_login.canceled'));

        $requestId = session()->pull(SamlIdp::REQUEST_ID);

        return $this->api->finishExternalLogin(
            SamlIdp::NAME,
            $stash['relayState'],
            fn (): ExternalIdentity => $this->reader->read($stash['response'], is_string($requestId) ? $requestId : null),
        );
    }
}
