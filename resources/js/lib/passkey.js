/**
 * WebAuthn のブラウザ側処理。
 *
 * サーバは base64url の文字列でやり取りし、ブラウザは ArrayBuffer を要求する。
 * その変換をここに閉じ込める。
 */

/** @param {string} value base64url */
function toBuffer(value) {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '='));

    return Uint8Array.from(binary, (c) => c.charCodeAt(0));
}

/** @param {ArrayBuffer} buffer */
function toBase64Url(buffer) {
    const binary = String.fromCharCode(...new Uint8Array(buffer));

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function csrfToken() {
    return decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}

/**
 * この端末にパスキーを登録する
 */
export async function registerPasskey(label = null) {
    if (!window.PublicKeyCredential) {
        throw new Error('このブラウザはパスキーに対応していません');
    }

    const optionsResponse = await fetch('/security/passkey/options', {
        method: 'POST',
        headers: { 'X-XSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    if (!optionsResponse.ok) throw new Error('チャレンジを取得できませんでした');

    const options = await optionsResponse.json();

    const credential = await navigator.credentials.create({
        publicKey: {
            ...options,
            challenge: toBuffer(options.challenge),
            user: { ...options.user, id: toBuffer(options.user.id) },
            excludeCredentials: (options.excludeCredentials ?? []).map((c) => ({ ...c, id: toBuffer(c.id) })),
        },
    });

    if (credential === null) throw new Error('登録がキャンセルされました');

    const payload = {
        id: credential.id,
        rawId: toBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: toBase64Url(credential.response.clientDataJSON),
            attestationObject: toBase64Url(credential.response.attestationObject),
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
