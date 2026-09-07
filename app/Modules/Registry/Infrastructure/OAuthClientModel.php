<?php
namespace App\Modules\Registry\Infrastructure;

use App\Modules\Registry\Domain\ServiceTrust;
use Illuminate\Database\Eloquent\Model;

/**
 * oauth_clients テーブルのモデル (ChreeID に接続するサービス)
 *
 * @property string $id
 * @property string|null $secret_hash
 * @property string $name
 * @property string|null $homepage_url
 * @property list<string> $redirect_uris
 * @property string $scopes
 * @property bool $is_confidential
 * @property ServiceTrust $trust
 */
class OAuthClientModel extends Model {
    protected $table = 'oauth_clients';

    // client_id は自前で採番するので自動連番ではない
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'secret_hash',
        'name',
        'homepage_url',
        'redirect_uris',
        'scopes',
        'is_confidential',
        'trust',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'is_confidential' => 'boolean',
        'trust' => ServiceTrust::class,
    ];

    /**
     * リダイレクト先が登録済みか。
     *
     * 部分一致や前方一致を許すとオープンリダイレクタになるので、完全一致で照合する。
     *
     * @param string $uri RP が指定した redirect_uri
     * @return bool
     */
    public function allowsRedirectUri(string $uri): bool {
        return in_array($uri, $this->redirect_uris, true);
    }

    /**
     * 要求されたスコープが許可範囲に収まっているか。
     *
     * @param list<string> $scopes 要求されたスコープ
     * @return bool
     */
    public function allowsScopes(array $scopes): bool {
        $allowed = explode(' ', $this->scopes);

        return array_diff($scopes, $allowed) === [];
    }

    /**
     * PKCE を必須とするか。public クライアントは秘密を持てないので必須。
     *
     * @return bool
     */
    public function requiresPkce(): bool {
        return !$this->is_confidential;
    }
}
