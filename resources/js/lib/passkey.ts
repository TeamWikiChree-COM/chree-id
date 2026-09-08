/**
 * WebAuthn のブラウザ側処理。
 *
 * サーバは base64url の文字列でやり取りし、ブラウザは ArrayBuffer を要求する。
 * その変換をここに閉じ込める。
 */

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
 * この端末にパスキーを登録する
 *
 * @param label 端末につける名前。省略するとサーバ側の既定になる
 * @throws Error 非対応ブラウザ・キャンセル・サーバ側の失敗
 */
export async function registerPasskey(label: string | null = null): Promise<void> {
    if (!window.PublicKeyCredential) {
        throw new Error('このブラウザはパスキーに対応していません');
    }

    const optionsResponse = await fetch('/security/passkey/options', {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    if (!optionsResponse.ok) throw new Error('チャレンジを取得できませんでした');

    const options = (await optionsResponse.json()) as RegistrationOptions;

    const credential = await navigator.credentials.create({
        publicKey: {
            ...options,
            challenge: toBuffer(options.challenge),
            user: { ...options.user, id: toBuffer(options.user.id) },
            excludeCredentials: (options.excludeCredentials ?? []).map((c) => ({ ...c, id: toBuffer(c.id) })),
        },
    });

    if (credential === null) throw new Error('登録がキャンセルされました');

    // credentials.create() の戻りは Credential 止まりなので、WebAuthn の形まで絞る
    const publicKeyCredential = credential as PublicKeyCredential;
    const response = publicKeyCredential.response as AuthenticatorAttestationResponse;

    const payload = {
        id: publicKeyCredential.id,
        rawId: toBase64Url(publicKeyCredential.rawId),
        type: publicKeyCredential.type,
        response: {
            clientDataJSON: toBase64Url(response.clientDataJSON),
            attestationObject: toBase64Url(response.attestationObject),
        },
    };

    const registerResponse = await fetch('/security/passkey/register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({ credential: JSON.stringify(payload), label }),
    });

    if (!registerResponse.ok) throw new Error('パスキーを登録できませんでした');
}
