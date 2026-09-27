<?php
namespace App\Modules\Provider\Http;

use App\Modules\Client\Infrastructure\OAuthClientModel;
use App\Modules\Linking\Infrastructure\ServiceAccountModel;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * サービスへのサインインの途中で出す画面。
 *
 * OIDC と、プラグインから頼まれたサインインで同じ画面を使う。
 * 違うのは許可したときの送り先 (action) と、一緒に送り返す値 (query) だけ。
 */
class SignInPages {
    /**
     * @param OAuthClientModel $client
     * @param list<string> $scopes
     * @param string $action 許可したときの送り先
     * @param array<string, mixed> $query 送り先へそのまま送り返す値
     * @return Response
     */
    public function consent(OAuthClientModel $client, array $scopes, string $action, array $query): Response {
        return Inertia::render('Oauth/Consent', [
            'clientName' => $client->displayName(),
            'clientIconUrl' => $client->icon_url,
            'scopes' => $scopes,
            'action' => $action,
            'query' => $query,
        ]);
    }

    /**
     * @param OAuthClientModel $client
     * @param Collection<int, ServiceAccountModel> $candidates
     * @param string $action 選んだときの送り先
     * @param array<string, mixed> $query 送り先へそのまま送り返す値
     * @return Response
     */
    public function chooseAccount(OAuthClientModel $client, Collection $candidates, string $action, array $query): Response {
        $accounts = $candidates
            ->map(fn (ServiceAccountModel $account): array => [
                'id' => $account->id,
                'serviceUserId' => $account->service_user_id,
                'connectedAt' => $account->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Oauth/ChooseAccount', [
            'clientName' => $client->displayName(),
            'clientIconUrl' => $client->icon_url,
            'accounts' => $accounts,
            'action' => $action,
            'query' => $query,
        ]);
    }
}
