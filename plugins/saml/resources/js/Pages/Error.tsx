import Typography from '@mui/material/Typography';
import AuthLayout from '@/Components/AuthLayout';
import { t } from '../lib/i18n';

/** IdpController::error() が渡す理由 */
type ErrorCode = 'invalid_request' | 'unknown_sp' | 'acs_mismatch' | 'bad_signature' | 'expired';

interface ErrorProps {
    code: ErrorCode;
}

const REASONS: Record<ErrorCode, string> = {
    invalid_request: t('error.invalid_request'),
    unknown_sp: t('error.unknown_sp'),
    acs_mismatch: t('error.acs_mismatch'),
    bad_signature: t('error.bad_signature'),
    expired: t('error.expired'),
};

/**
 * SAML のサービスから来た要求を受けられなかったとき。
 *
 * SP へエラーの Response を返さないのは、どこへ返すかを要求の中身から信じることになるため。
 */
export default function SamlError({ code }: ErrorProps) {
    return (
        <AuthLayout title={t('error.title')} heading={t('error.title')}>
            <Typography variant="body2">{REASONS[code]}</Typography>
        </AuthLayout>
    );
}
