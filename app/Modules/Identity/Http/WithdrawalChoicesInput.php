<?php
namespace App\Modules\Identity\Http;

use App\Modules\Identity\Application\WithdrawalChoices;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 退会の画面から届いた、サービスごとの選択を読む。
 *
 * **画面から来た値は信用しない。** 並べたサービスすべてに選択があること、
 * 削除は依頼できるサービスに限ること、解放には持っていける認証手段が要ることをここで確かめる。
 */
class WithdrawalChoicesInput {
    public const DELETE = 'delete';

    public const RELEASE = 'release';

    /**
     * @param Request $request
     * @param list<array{id: string, deletable: bool}> $services 画面に並べたサービス
     * @param list<string> $credentialIds 持っていける認証手段のID
     * @return WithdrawalChoices
     * @throws ValidationException
     */
    public static function read(Request $request, array $services, array $credentialIds): WithdrawalChoices {
        $input = $request->input('services', []);
        $input = is_array($input) ? $input : [];

        $deletions = [];
        $releases = [];
        $errors = [];

        foreach ($services as $service) {
            $choice = is_array($input[$service['id']] ?? null) ? $input[$service['id']] : [];
            $action = $choice['action'] ?? null;
            $key = "services.{$service['id']}";

            if ($action === self::DELETE && $service['deletable']) {
                $deletions[] = $service['id'];
                continue;
            }

            if ($action !== self::RELEASE) {
                $errors[$key] = __('identity.withdraw.choice_required');
                continue;
            }

            $carried = self::carried($choice['credentials'] ?? [], $credentialIds);
            if ($carried === []) $errors[$key] = __('linking.split.no_credential');

            $releases[$service['id']] = $carried;
        }

        if ($errors !== []) throw ValidationException::withMessages($errors);

        return new WithdrawalChoices($deletions, $releases);
    }

    /**
     * @param mixed $chosen 画面で選ばれたID
     * @param list<string> $credentialIds 持っていける認証手段のID
     * @return list<string>
     */
    private static function carried(mixed $chosen, array $credentialIds): array {
        if (!is_array($chosen)) return [];

        return array_values(array_intersect($credentialIds, array_filter($chosen, 'is_string')));
    }
}
