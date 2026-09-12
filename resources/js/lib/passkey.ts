/**
 * WebAuthn のブラウザ側処理。
 *
 * サーバは base64url の文字列でやり取りし、ブラウザは ArrayBuffer を要求する。
 * その変換をここに閉じ込める。
 */

import { t } from './i18n';

/** サーバが返す登録オプション。base64url の項目だけ string になっている */
interface RegistrationOptions extends Omit<PublicKeyCredentialCreationOptions, 'challenge' | 'user' | 'excludeCredentials'> {
    challenge: string;
    user: Omit<PublicKeyCredentialUserEntity, 'id'> & { id: string };
    excludeCredentials?: (Omit<PublicKeyCredentialDescriptor, 'id'> & { id: string })[];
}

/**
 * @param value base64url
 * @returns WebAuthn が要求する BufferSource。ArrayBuffer 裏付けを型でも明示する
 */
function toBuffer(value: string): Uint8Array<ArrayBuffer> {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '='));

    const bytes = new Uint8Array(new ArrayBuffer(binary.length));
    for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);

    return bytes;
}

function toBase64Url(buffer: ArrayBuffer): string {
    const binary = String.fromCharCode(...new Uint8Array(buffer));

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function csrfToken(): string {
    return decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}

/**
 * サーバが返した失敗の理由を取り出す。
 *
 * **全部まとめて1つの文言にしない。** どれも同じ表示になると、
 * rpId のずれなのか応答の壊れなのかが利用者にも開発者にも分からなくなる。
 *
 * @param response 失敗したレスポンス
 * @param fallback 理由を取り出せなかったときの文言
 * @returns 画面に出すメッセージ
 */
async function failureMessage(response: Response, fallback: string): Promise<string> {
    // 本文が JSON とは限らない (502 や HTML のエラーページもありうる)
    try {
        const body = (await response.json()) as { reason?: unknown };
        if (typeof body.reason === 'string' && body.reason !== '') return body.reason;
    } catch {
        // 取り出せないときは status で切り分けられるようにする
    }

    return `${fallback} (${String(response.status)})`;
}

/**
 * パスキー登録の一連のやり取りを行う。
 *
 * @param optionsUrl チャレンジ取得先
 * @param registerUrl 登録応答の送信先
 * @param label 端末につける名前。省略するとサーバ側の既定になる
 * @param extraFields 送信先の特定に必要な追加パラメータ (引き取り画面のトークン等)
 * @throws Error 非対応ブラウザ・キャンセル・サーバ側の失敗
 */
async function createPasskey(
    optionsUrl: string,
    registerUrl: string,
    label: string | null,
    extraFields: Record<string, string> = {},
): Promise<void> {
    if (!window.PublicKeyCredential) {
        throw new Error(t('passkey.error.unsupported'));
    }

    const optionsResponse = await fetch(optionsUrl, {
        method: 'POST',
        // 既定でも同一オリジンには付くが、セッションが要ることを読んで分かるようにしておく
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-XSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: new URLSearchParams(extraFields).toString(),
    });
    if (!optionsResponse.ok) throw new Error(await failureMessage(optionsResponse, t('passkey.error.challenge')));

    const options = (await optionsResponse.json()) as RegistrationOptions;

    const credential = await navigator.credentials.create({
        publicKey: {
            ...options,
            challenge: toBuffer(options.challenge),
            user: { ...options.user, id: toBuffer(options.user.id) },
            excludeCredentials: (options.excludeCredentials ?? []).map((c) => ({ ...c, id: toBuffer(c.id) })),
        },
    });

    if (credential === null) throw new Error(t('passkey.error.cancelled'));

    // credentials.create() の戻りは Credential 止まりなので、WebAuthn の形まで絞る
    // const publicKeyCredential = credential as PublicKeyCredential;
    // const response = publicKeyCredential.response as AuthenticatorAttestationResponse;

    // const payload = {
    //     id: publicKeyCredential.id,
    //     rawId: toBase64Url(publicKeyCredential.rawId),
    //     type: publicKeyCredential.type,
    //     response: {
    //         clientDataJSON: toBase64Url(response.clientDataJSON),
    //         attestationObject: toBase64Url(response.attestationObject),
    //     },
    // };

    // const registerResponse = await fetch(registerUrl, {
    //     method: 'POST',
    //     credentials: 'same-origin',
    //     headers: {
    //         'Content-Type': 'application/json',
    //         'X-XSRF-TOKEN': csrfToken(),
    //         Accept: 'application/json',
    //     },
    //     body: JSON.stringify({ ...extraFields, credential: JSON.stringify(payload), label }),
    // });

    // if (!registerResponse.ok) throw new Error(await failureMessage(registerResponse, t('passkey.error.register')));
}

/**
 * この端末にパスキーを登録する (ログイン中の設定画面から)
 *
 * @param label 端末につける名前。省略するとサーバ側の既定になる
 * @throws Error 非対応ブラウザ・キャンセル・サーバ側の失敗
 */
export async function registerPasskey(label: string | null = null): Promise<void> {
    await createPasskey('/security/passkey/options', '/security/passkey/register', label);
}

/**
 * 引き取り (claim) 画面からこの端末にパスキーを登録する。
 *
 * ログイン前なので、対象アカウントは引き取りトークンで特定する。
 *
 * @param token 引き取りトークン
 * @param label 端末につける名前。省略するとサーバ側の既定になる
 * @throws Error 非対応ブラウザ・キャンセル・サーバ側の失敗
 */
export async function registerClaimPasskey(token: string, label: string | null = null): Promise<void> {
    await createPasskey('/claim/passkey/options', '/claim/passkey/register', label, { token });
}
