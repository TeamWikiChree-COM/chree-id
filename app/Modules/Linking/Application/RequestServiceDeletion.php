<?php
namespace App\Modules\Linking\Application;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Linking\Domain\ServiceDeletionStatus;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use App\Modules\Linking\Infrastructure\ServiceDeletionRequestModel;

/**
 * 退会に合わせて、サービス側のアカウントの削除を依頼として積む。
 *
 * ここでは送らない。退会のトランザクションの中で呼ばれるので、外部への通信は
 * コミット後に `DeliverServiceDeletions` が行う。
 */
class RequestServiceDeletion {
    /**
     * このサービスアカウントに削除を依頼できるか。
     *
     * サービスが依頼 URL を持たない、または相手が誰かを伝える手がかりが無ければ依頼できない。
     *
     * @param ServiceAccountModel $serviceAccount
     * @param OAuthClientModel|null $client
     * @return bool
     */
    public function supports(ServiceAccountModel $serviceAccount, ?OAuthClientModel $client): bool {
        if ($client === null || $client->deletion_request_uri === null) return false;

        return $serviceAccount->sub !== null || $serviceAccount->service_user_id !== null;
    }

    /**
     * @param ServiceAccountModel $serviceAccount 消してもらうサービスアカウント
     * @param string|null $notifyEmail 断られたときの通知先
     * @return ServiceDeletionRequestModel
     */
    public function execute(ServiceAccountModel $serviceAccount, ?string $notifyEmail): ServiceDeletionRequestModel {
        return ServiceDeletionRequestModel::create([
            'client_id' => $serviceAccount->client_id,
            'sub' => $serviceAccount->sub,
            'service_user_id' => $serviceAccount->service_user_id,
            'notify_email' => $notifyEmail,
            'status' => ServiceDeletionStatus::PENDING,
        ]);
    }
}
