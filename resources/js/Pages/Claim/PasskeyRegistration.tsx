import Alert from "@mui/material/Alert";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import { useState } from "react";
import Icon from "../../Components/Icon";
import { registerClaimPasskey } from "../../lib/passkey";
import { t } from "../../lib/i18n";

interface PasskeyRegistrationProps {
    /** 移行のトークン。登録をこの移行に結びつける */
    token: string;
    registered: boolean;
    onRegistered: () => void;
    /** サーバが返した method の誤り */
    error?: string;
}

/**
 * 移行画面で、ログイン方法としてパスキーを登録する欄。
 */
export default function PasskeyRegistration({ token, registered, onRegistered, error }: PasskeyRegistrationProps) {
    const [passkeyError, setPasskeyError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const register = async (): Promise<void> => {
        setPasskeyError(null);
        setBusy(true);
        try {
            await registerClaimPasskey(token);
            onRegistered();
        } catch (e) {
            setPasskeyError(e instanceof Error ? e.message : t('claim.show.passkey.register_failed'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <Stack spacing={1}>
            {passkeyError && <Alert severity="error">{passkeyError}</Alert>}
            {error && <Alert severity="error">{error}</Alert>}
            {registered ? (
                <Alert severity="success">{t('claim.show.passkey.registered')}</Alert>
            ) : (
                <Button
                    variant="outlined"
                    color="inherit"
                    startIcon={<Icon name="fingerprint" />}
                    disabled={busy}
                    onClick={() => void register()}
                >
                    {t('claim.show.passkey.register')}
                </Button>
            )}
        </Stack>
    );
}
