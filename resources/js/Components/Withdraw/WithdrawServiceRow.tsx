import Box from '@mui/material/Box';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import Typography from '@mui/material/Typography';
import LinkedText from '../LinkedText';
import ServiceIcon from '../ServiceIcon';
import { t } from '../../lib/i18n';
import { splitCredentialLabel, type SplitOption } from '../../lib/splitCredentials';
import type { DeletionCheck, WithdrawalChoice, WithdrawalService } from './types';

interface WithdrawServiceRowProps {
    service: WithdrawalService;
    choice: WithdrawalChoice;
    onChange: (choice: WithdrawalChoice) => void;
    /** 持っていける認証方法 */
    credentials: SplitOption[];
    /** 削除の問い合わせの結果。undefined は確認中、null は答えなし */
    check: DeletionCheck | null | undefined;
    error?: string;
}

const NOTE_SX = { fontSize: '0.8125rem', color: 'text.secondary' } as const;

/**
 * 退会の画面で、連携サービス1件をどうするかを選ばせる行。
 *
 * 削除を依頼できるサービスにだけ「削除 / 解放」を出す。依頼できないサービスは解放するしかない。
 */
export default function WithdrawServiceRow({ service, choice, onChange, credentials, check, error }: WithdrawServiceRowProps) {
    const refused = check !== undefined && check !== null && !check.accepted;
    const canRelease = credentials.length > 0;

    const toggle = (id: string): void => {
        const selected = choice.credentials.includes(id) ? choice.credentials.filter((chosen) => chosen !== id) : [...choice.credentials, id];
        onChange({ ...choice, credentials: selected });
    };

    return (
        <Box sx={{ px: 2, py: 1.5 }}>
            <Box sx={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 1.5 }}>
                <ServiceIcon name={service.name} iconUrl={service.iconUrl} />
                <Typography sx={{ fontSize: '0.9375rem', flexGrow: 1 }}>{service.name}</Typography>

                {service.deletable ? (
                    <RadioGroup row value={choice.action} onChange={(e) => onChange({ ...choice, action: e.target.value as WithdrawalChoice['action'] })}>
                        <FormControlLabel value="delete" control={<Radio size="small" />} label={t('settings.withdraw.services.delete')} disabled={refused} />
                        <FormControlLabel value="release" control={<Radio size="small" />} label={t('settings.withdraw.services.release')} disabled={!canRelease} />
                    </RadioGroup>
                ) : (
                    <Typography sx={NOTE_SX}>{t('settings.withdraw.services.release_only')}</Typography>
                )}
            </Box>

            {service.deletable && <DeletionNote check={check} />}

            {choice.action === 'release' && canRelease && (
                <Box sx={{ mt: 1 }}>
                    <Typography sx={NOTE_SX}>{t('settings.withdraw.services.carry')}</Typography>
                    {credentials.map((option) => (
                        <FormControlLabel
                            key={option.id}
                            control={<Checkbox size="small" checked={choice.credentials.includes(option.id)} onChange={() => toggle(option.id)} />}
                            label={splitCredentialLabel(option)}
                        />
                    ))}
                </Box>
            )}

            {!canRelease && (
                <Typography sx={{ ...NOTE_SX, mt: 1 }}>
                    <LinkedText text={t('settings.withdraw.services.no_credential')} />
                </Typography>
            )}

            {error && <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'error.main' }}>{error}</Typography>}
        </Box>
    );
}

/**
 * 削除を依頼したときにどうなるか。サービスの答えをそのまま伝える。
 */
function DeletionNote({ check }: { check: DeletionCheck | null | undefined }) {
    if (check === undefined) return <Typography sx={{ ...NOTE_SX, mt: 0.5 }}>{t('settings.withdraw.services.checking')}</Typography>;

    if (check !== null && !check.accepted) {
        return (
            <Typography sx={{ ...NOTE_SX, mt: 0.5 }}>
                {t('settings.withdraw.services.refused')}
                {check.reason !== null && ` ${check.reason}`}
            </Typography>
        );
    }

    const text = check?.graceDays != null
        ? t('settings.withdraw.services.grace', { days: check.graceDays })
        : t('settings.withdraw.services.grace_unknown');

    return <Typography sx={{ ...NOTE_SX, mt: 0.5 }}>{text}</Typography>;
}
