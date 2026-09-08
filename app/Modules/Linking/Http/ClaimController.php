<?php
namespace App\Modules\Linking\Http;

use App\Modules\Identity\Domain\ChreeAccountRepository;
use App\Modules\Identity\Infrastructure\ChreeSession;
use App\Modules\Linking\Application\ClaimException;
use App\Modules\Linking\Application\ClaimServiceAccount;
use App\Modules\Linking\Application\ClaimTickets;
use App\Modules\Linking\Infrastructure\ServiceAccountLinkModel;
use App\Modules\Registry\Infrastructure\OAuthClientModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 裏で作られたアカウントを本人が引き取る画面。
 *
 * 利用者から見ると「ChreeID を作成」だが、実際には既にあるものを
 * 自分のものにする操作。サービス側で使ってきた分がそのまま残る。
 */
class ClaimController {
    /** 理由ごとの表示。内部の理由コードはそのまま出さない */
    private const FAILURE_MESSAGES = [
        ClaimException::INVALID_TICKET => 'このリンクは使えません。お手数ですが、サービスの設定画面からやり直してください',
        ClaimException::ALREADY_CLAIMED => 'このアカウントは既に ChreeID として使えます。ログインをお試しください',
        ClaimException::EMAIL_TAKEN => 'このメールアドレスは既に使われています',
    ];

    public function __construct(
        private readonly ClaimTickets $tickets,
        private readonly ClaimServiceAccount $claim,
        private readonly ChreeAccountRepository $accounts,
        private readonly ChreeSession $session,
    ) {}

    /**
     * 入場券の着地。ここでパスワードを決めてもらう。
     *
     * @param string $token URL に載っていた平文トークン
     * @return Response
     */
    public function show(string $token): Response {
        $link = $this->tickets->find($token);
        if ($link === null) return $this->failed(ClaimException::INVALID_TICKET);
        if ($link->isClaimed()) return $this->failed(ClaimException::ALREADY_CLAIMED);

        $account = $this->accounts->findById($link->chree_account_id);
        if ($account === null) return $this->failed(ClaimException::INVALID_TICKET);

        return Inertia::render('Claim/Show', [
            'token' => $token,
            'serviceName' => $this->serviceName($link),
            'email' => $account->email,
            'emailVerified' => $account->isEmailVerified(),
            'displayName' => $account->displayName,
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|Response {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $link = $this->tickets->find($request->string('token')->toString());
        if ($link === null) return $this->failed(ClaimException::INVALID_TICKET);

        $displayName = $request->string('display_name')->trim()->toString();
        $email = $request->string('email')->trim()->toString();

        try {
            $accountId = $this->claim->execute(
                $link,
                $request->string('password')->toString(),
                $displayName === '' ? null : $displayName,
                $email === '' ? null : $email,
            );
        } catch (ClaimException $e) {
            if ($e->reason === ClaimException::EMAIL_TAKEN) {
                throw ValidationException::withMessages(['email' => self::FAILURE_MESSAGES[$e->reason]]);
            }

            return $this->failed($e->reason);
        }

        // 本人がパスワードを決めた直後なので、ここはログインさせてよい
        $this->session->login($accountId);

        return redirect('/')->with('claimed', true);
    }

    /**
     * @param ServiceAccountLinkModel $link 対象の紐付け
     * @return string 利用者に見せるサービス名
     */
    private function serviceName(ServiceAccountLinkModel $link): string {
        // client_id は外部キーなので、紐付けがある限り必ず引ける
        return OAuthClientModel::query()->findOrFail($link->client_id)->name;
    }

    /**
     * @param string $reason ClaimException の理由コード
     * @return Response
     */
    private function failed(string $reason): Response {
        return Inertia::render('Claim/Failed', ['message' => self::FAILURE_MESSAGES[$reason]]);
    }
}
