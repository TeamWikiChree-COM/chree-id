<?php
namespace Plugins\Saml\Idp;

use App\Modules\Client\Infrastructure\OAuthClientModel;

/**
 * SAML でつなぐサービス (SP) の登録・変更。
 *
 * artisan コマンドと管理画面の両方から使う。
 * 入力の誤りは文言ではなくコードで返す。プラグインにはサーバ側の辞書が無いので、画面側でプラグインの辞書から文言にする。
 */
class ServiceProviders {
    /**
     * @param array{client_id: string, entity_id: string, acs_url: string, certificate: string, scopes: string} $input
     * @param ServiceProviderModel|null $current 変更なら今の行。新規なら null
     * @return array<string, string> 項目名 => 誤りのコード。空なら保存してよい
     */
    public function check(array $input, ?ServiceProviderModel $current = null): array {
        $errors = [];

        $client = OAuthClientModel::query()->find($input['client_id']);
        if ($client === null) $errors['client_id'] = 'client_unknown';
        elseif ($this->taken('client_id', $input['client_id'], $current)) $errors['client_id'] = 'client_taken';

        if ($input['entity_id'] === '') $errors['entity_id'] = 'required';
        elseif ($this->taken('entity_id', $input['entity_id'], $current)) $errors['entity_id'] = 'entity_id_taken';

        // http の ACS へ送ると、署名していても Assertion を盗み見られる
        if (!$this->isHttpsUrl($input['acs_url'])) $errors['acs_url'] = 'acs_url_invalid';

        if ($input['certificate'] !== '' && openssl_x509_read($input['certificate']) === false) $errors['certificate'] = 'certificate_invalid';

        // 本体はサービスに許した範囲の外を頼まれると断る。ログインのたびに落ちる前に、ここで止める
        if ($client !== null && !$client->allowsScopes($this->scopes($input['scopes']))) $errors['scopes'] = 'scopes_not_allowed';

        return $errors;
    }

    /**
     * check() を通った入力を保存する。
     *
     * @param array{client_id: string, entity_id: string, acs_url: string, certificate: string, scopes: string} $input
     * @param ServiceProviderModel|null $current
     * @return ServiceProviderModel
     */
    public function save(array $input, ?ServiceProviderModel $current = null): ServiceProviderModel {
        $sp = $current ?? new ServiceProviderModel();
        $sp->fill([
            'client_id' => $input['client_id'],
            'entity_id' => $input['entity_id'],
            'acs_url' => $input['acs_url'],
            'certificate' => $input['certificate'] === '' ? null : $input['certificate'],
            'scopes' => implode(' ', $this->scopes($input['scopes'])),
        ])->save();

        return $sp;
    }

    /**
     * @param string $scopes 空白区切り
     * @return list<string>
     */
    private function scopes(string $scopes): array {
        return array_values(array_filter(preg_split('/\s+/', trim($scopes)) ?: [], static fn (string $s): bool => $s !== ''));
    }

    /**
     * @param string $column
     * @param string $value
     * @param ServiceProviderModel|null $current
     * @return bool ほかの SP が既に使っているか
     */
    private function taken(string $column, string $value, ?ServiceProviderModel $current): bool {
        return ServiceProviderModel::query()
            ->where($column, $value)
            ->when($current !== null, static fn ($q) => $q->whereKeyNot($current?->id))
            ->exists();
    }

    /**
     * @param string $url
     * @return bool
     */
    private function isHttpsUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with($url, 'https://');
    }
}
