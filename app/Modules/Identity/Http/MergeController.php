<?php
namespace App\Modules\Identity\Http;

use App\Modules\Audit\Application\AuditLog;
use App\Modules\Audit\Domain\AuditAction;
use App\Modules\Credential\Application\VerifyCredential;
use App\Modules\Credential\Domain\CredentialRepository;
use App\Modules\Credential\Domain\CredentialType;
use App\Modules\Credential\Domain\VerifiedFactors;
use App\Modules\Identity\Application\MergeAccounts;
use App\Modules\Identity\Application\MergeException;
use App\Modules\Identity\Application\SuggestMergeCandidates;
use App\Modules\Identity\Application\TransferableCredentials;
use App\Modules\Identity\Infrastructure\ChreeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 候補として挙がった別アカウントを、いまのアカウントへ寄せる。
 *
 * **提示と実行は別処理** (ARCHITECTURE.md 8.7)。候補に挙がっていることは
 * 実行の根拠にならない。同じアドレスを使っているだけの別人ということが普通にある。
 *
 * 実行の根拠は「両側を証明できているか」。こちら側はログインしていること、
 * 相手側は**その認証手段を通せること**で示す。**メールの一致は証明にならない。**
 * 候補は同じアドレスのアカウントなので、メールに送っても同じ受信箱に届く。
 */
class MergeController {
    /** 相手側の証明に使える方式。パスキーは儀式がブラウザ側に要るので、ここでは扱わない */
    private const PROOFS = [CredentialType::PASSWORD, CredentialType::TOTP];

    private readonly ChreeSession $session;
    private readonly SuggestMergeCandidates $candidates;
    private readonly CredentialRepository $credentials;
    private readonly VerifyCredential $verify;
    private readonly TransferableCredentials $transferable;
    private readonly MergeAccounts $merge;
    private readonly AuditLog $audit;

    public function __construct(
        ChreeSession $session,
        SuggestMergeCandidates $candidates,
        CredentialRepository $credentials,
        VerifyCredential $verify,
        TransferableCredentials $transferable,
        MergeAccounts $merge,
        AuditLog $audit,
    ) {
        $this->session = $session;
        $this->candidates = $candidates;
        $this->credentials = $credentials;
        $this->verify = $verify;
        $this->transferable = $transferable;
        $this->merge = $merge;
        $this->audit = $audit;
    }

    /**
     * @param string $candidate 寄せ元のアカウントID (ULID)
     * @return Response|RedirectResponse
     */
    public function show(string $candidate): Response|RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $found = $this->candidateOf($accountId, $candidate);
        if ($found === null) return redirect('/');

        return Inertia::render('Settings/Merge', [
            'candidate' => $found,
            'proofs' => $this->proofsFor($candidate),
            'transferable' => $this->transferableFor($candidate, $accountId),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException 証明できなかった場合
     */
    public function store(Request $request): RedirectResponse {
        $accountId = $this->session->accountId();
        if ($accountId === null) return redirect('/login');

        $request->validate([
            'candidate' => ['required', 'string'],
            'proof' => ['required', 'string', 'in:password,totp'],
            'secret' => ['required', 'string'],
            'credentials' => ['array'],
            'credentials.*' => ['string'],
        ]);

        $candidate = $request->string('candidate')->toString();

        // **候補の一覧に無いIDは通さない。** 画面から戻ってきたIDは信用しない
        if ($this->candidateOf($accountId, $candidate) === null) return redirect('/');

        $this->assertProven($candidate, $request);

        /** @var list<string> $chosen */
        $chosen = $request->input('credentials', []);

        try {
            $this->merge->execute($candidate, $accountId, $this->transferable->accept($candidate, $accountId, $chosen));
        } catch (MergeException $e) {
            // 例外が持つのは理由の記号。そのまま出すと画面に same_account と出る
            throw ValidationException::withMessages(['secret' => __("settings.merge.failed.{$e->reason}")]);
        }

        $this->audit->record(AuditAction::ACCOUNT_MERGED, $accountId, ['source' => $candidate]);

        return redirect('/')->with('accountMerged', true);
    }

    /**
     * 相手側が本人のものだと確かめる。
     *
     * @param string $candidate 寄せ元のアカウントID (ULID)
     * @param Request $request
     * @return void
     * @throws ValidationException 通らなかった場合
     */
    private function assertProven(string $candidate, Request $request): void {
        $type = CredentialType::from($request->string('proof')->toString());
        $secret = $request->string('secret')->toString();

        $input = $type === CredentialType::PASSWORD ? ['password' => $secret] : ['code' => $secret];

        $result = $this->verify->execute($candidate, $type, $input, new VerifiedFactors());
        if ($result->isSuccess()) return;

        throw ValidationException::withMessages(['secret' => __('settings.merge.not_proven')]);
    }

    /**
     * @param string $accountId いまログインしているアカウントID (ULID)
     * @param string $candidate 寄せ元のアカウントID (ULID)
     * @return array{id: string, displayName: string|null, email: string|null, hasUserAccount: bool}|null
     */
    private function candidateOf(string $accountId, string $candidate): ?array {
        foreach ($this->candidates->execute($accountId) as $found) {
            if ($found['id'] === $candidate) return $found;
        }

        return null;
    }

    /**
     * 相手側の証明に使える方式。
     *
     * 1つも無い相手とは統合できない。**証明できないまま寄せてはいけない。**
     *
     * @param string $candidate 寄せ元のアカウントID (ULID)
     * @return list<string>
     */
    private function proofsFor(string $candidate): array {
        $available = [];
        foreach (self::PROOFS as $type) {
            if ($this->credentials->has($candidate, $type)) $available[] = $type->value;
        }

        return $available;
    }

    /**
     * @param string $candidate 寄せ元のアカウントID (ULID)
     * @param string $accountId 寄せ先のアカウントID (ULID)
     * @return list<array{id: string, type: string, label: string|null}>
     */
    private function transferableFor(string $candidate, string $accountId): array {
        $result = [];
        foreach ($this->transferable->execute($candidate, $accountId) as $credential) {
            $data = is_array($credential->data) ? $credential->data : [];
            $label = $data['label'] ?? null;

            $result[] = [
                'id' => $credential->id,
                'type' => $credential->type->value,
                'label' => is_string($label) ? $label : null,
            ];
        }

        return $result;
    }
}
