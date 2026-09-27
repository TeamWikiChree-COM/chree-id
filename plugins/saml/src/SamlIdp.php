<?php
namespace Plugins\Saml;

use App\Modules\ExternalLogin\Domain\ExternalIdp;
use App\Modules\ExternalLogin\Domain\ExternalIdpDisplay;
use OneLogin\Saml2\Auth;

/**
 * 外部の SAML IdP (ChreeID が SP 側)。
 *
 * 送り出しは本体の /auth/saml/redirect が受け持ち、ここで AuthnRequest を組み立てる。
 * 戻りは認可コードではなく POST なので、SamlController が受ける。
 */
class SamlIdp implements ExternalIdp {
    public const NAME = 'saml';

    /** 出した AuthnRequest の ID。戻ってきた Response の InResponseTo と照らす */
    public const REQUEST_ID = 'saml.request_id';

    private readonly SamlSettings $settings;

    public function __construct(SamlSettings $settings) {
        $this->settings = $settings;
    }

    /**
     * @return string
     */
    public function name(): string {
        return self::NAME;
    }

    /**
     * @return bool
     */
    public function isConfigured(): bool {
        return $this->settings->isConfigured();
    }

    /**
     * 名前は IdP ごとに違う (社名など) ので設定から取る。
     *
     * @return ExternalIdpDisplay
     */
    public function display(): ExternalIdpDisplay {
        return new ExternalIdpDisplay($this->settings->label(), 'building');
    }

    /**
     * SAML に nonce は無いので、代わりに AuthnRequest の ID を預けて InResponseTo で照らす。
     *
     * @param string $state RelayState として往復させる
     * @param string $nonce 使わない
     * @return string
     */
    public function authorizationUrl(string $state, string $nonce): string {
        $auth = new Auth($this->settings->toArray());
        $url = (string) $auth->login($state, [], false, false, true);
        session()->put(self::REQUEST_ID, $auth->getLastRequestID());

        return $url;
    }
}
