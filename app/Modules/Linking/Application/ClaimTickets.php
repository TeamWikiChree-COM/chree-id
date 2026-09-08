<?php
namespace App\Modules\Linking\Application;

use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Support\Str;

/**
 * 引き取り (claim) の入場券。
 *
 * 裏で発行したアカウントを本人のものにするには、本人だと分かっている
 * 誰かが橋渡しをしなければならない。ここではサービスがそれをやる。
 * サービスは自分のところでログイン中の利用者にだけ URL を渡す約束で、
 * こちらは一度きり・短命のトークンを返す。
 *
 * 平文を返すのは発行時の1回だけ。DB にはハッシュしか残さない。
 */
class ClaimTickets {
    /** 入場券の寿命。設定画面から踏むだけなので短くてよい */
    private const TTL_MINUTES = 15;

    /**
     * サービスの利用者に対する入場券を作り直す。
     *
     * @param OAuthClientModel $client 呼び出したサービス
     * @param string $serviceUserId サービス側での利用者の識別子
     * @return ClaimTicket|null 紐付けが無ければ null
     */
    public function issue(OAuthClientModel $client, string $serviceUserId): ?ClaimTicket {
        $link = ServiceAccountLinkModel::query()
            ->where('client_id', $client->id)
            ->where('service_user_id', $serviceUserId)
            ->first();

        if ($link === null) return null;

        // 前に配った券は使えなくする。有効なのは最後に渡した1枚だけ
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        $link->forceFill([
            'claim_token_hash' => hash('sha256', $token),
            'claim_expires_at' => $expiresAt,
        ])->save();

        return new ClaimTicket($link, $token, $expiresAt);
    }

    /**
     * @param string $token URL に載っていた平文トークン
     * @return ServiceAccountLinkModel|null 使えない券なら null
     */
    public function find(string $token): ?ServiceAccountLinkModel {
        $link = ServiceAccountLinkModel::query()
            ->where('claim_token_hash', hash('sha256', $token))
            ->first();

        if ($link === null) return null;
        if ($link->claim_expires_at === null || $link->claim_expires_at->isPast()) return null;

        return $link;
    }

    /**
     * 使い終わった券を捨てる。
     *
     * @param ServiceAccountLinkModel $link 対象の紐付け
     * @return void
     */
    public function consume(ServiceAccountLinkModel $link): void {
        $link->forceFill(['claim_token_hash' => null, 'claim_expires_at' => null])->save();
    }

    /**
     * @return int 券の有効分数
     */
    public function ttlMinutes(): int {
        return self::TTL_MINUTES;
    }
}
