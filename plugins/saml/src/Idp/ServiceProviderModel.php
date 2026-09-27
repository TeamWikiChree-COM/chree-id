<?php
namespace Plugins\Saml\Idp;

use Illuminate\Database\Eloquent\Model;

/**
 * ChreeID に SAML でつなぐサービス (SP) の設定。
 *
 * @property int $id
 * @property string $client_id 対応する oauth_clients の行
 * @property string $entity_id
 * @property string $acs_url
 * @property string|null $certificate SP の署名用の証明書 (PEM)
 * @property string $scopes 空白区切り
 */
class ServiceProviderModel extends Model {
    protected $table = 'saml_service_providers';

    protected $fillable = ['client_id', 'entity_id', 'acs_url', 'certificate', 'scopes'];

    /**
     * @return list<string>
     */
    public function scopeList(): array {
        return array_values(array_filter(explode(' ', $this->scopes), static fn (string $s): bool => $s !== ''));
    }
}
