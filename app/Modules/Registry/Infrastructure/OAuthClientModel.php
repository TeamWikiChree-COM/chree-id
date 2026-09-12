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
 * @property array<string, string>|null $names 言語ごとの表示名。無い言語は $name を出す
 * @property string|null $homepage_url
 * @property string|null $icon_url
 * @property string|null $settings_url 利用者に案内する「このサービスの設定」の場所
 * @property string|null $owner_id 登録した本人。運営が登録したものは null
 * @property \Illuminate\Support\Carbon|null $review_requested_at 承認を申請した日時
 * @property list<string> $redirect_uris
 * @property string $scopes
 * @property bool $is_confidential
 * @property ServiceTrust $trust
 * @property bool $skips_consent
 * @property bool $can_provision
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
        'names',
        'homepage_url',
        'icon_url',
        'settings_url',
        'owner_id',
        'review_requested_at',
        'redirect_uris',
        'scopes',
        'is_confidential',
        'trust',
        'skips_consent',
        'can_provision',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'names' => 'array',
        'is_confidential' => 'boolean',
        'skips_consent' => 'boolean',
        'can_provision' => 'boolean',
        'trust' => ServiceTrust::class,
        'review_requested_at' => 'datetime',
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

    /**
     * 利用者に出す名前。
     *
     * **`name` をそのまま出さない。** あれは運営が識別に使う名前で、
     * 言語ごとの表示名があるならそちらを優先する。無ければ `name` に落ちる
     * (どの言語でも何かしら出せることを、この1か所で保証する)。
     *
     * @param string|null $locale 出したい言語。省略すると現在の表示言語
     * @return string
     */
    public function displayName(?string $locale = null): string {
        $name = $this->names[$locale ?? app()->getLocale()] ?? null;

        return is_string($name) && trim($name) !== '' ? $name : $this->name;
    }
}
