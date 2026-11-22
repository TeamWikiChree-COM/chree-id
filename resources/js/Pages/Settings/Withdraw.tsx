import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AppLayout from '../../Components/AppLayout';
import InertiaLink from '../../Components/InertiaLink';
import RowDivider from '../../Components/RowDivider';
import SectionTitle from '../../Components/SectionTitle';
import WithdrawServiceRow from '../../Components/Withdraw/WithdrawServiceRow';
import type { DeletionCheck, WithdrawalChoice, WithdrawalService } from '../../Components/Withdraw/types';
import { t } from '../../lib/i18n';
import type { SplitOption } from '../../lib/splitCredentials';

interface WithdrawProps {
    email: string | null;
    /** 連携中のサービス */
    services: WithdrawalService[];
    /** 解放するときに持っていける認証方法 */
    credentials: SplitOption[];
    /** 削除できるかの問い合わせ結果。遅れて届く。答えなかったサービスは null */
    checks?: Record<string, DeletionCheck | null>;
    /** 消えるまでの日数 */
    graceDays: number;
}

/**
 * ChreeID の退会。
 *
 * 連携しているサービスごとに、削除を依頼するか解放するかを選ばせる。
 * 押し間違いを防ぐため、削除を依頼できるサービスには初期値を置かない。
 */
export default function Withdraw({ email, services, credentials, checks, graceDays }: WithdrawProps) {
    const { data, setData, post, processing, errors } = useForm<{ understood: boolean; services: Record<string, WithdrawalChoice> }>({
        understood: false,
        services: Object.fromEntries(services.map((service) => [service.id, initialChoice(service, credentials)])),
    });

    const choiceOf = (service: WithdrawalService): WithdrawalChoice => data.services[service.id] ?? initialChoice(service, credentials);
    const ready = services.every((service) => isDecided(service, choiceOf(service), credentials, checks?.[service.id]));
    const fieldErrors = errors as Record<string, string | undefined>;

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/settings/withdraw');
    };

    return (
        <AppLayout
            title={t('settings.withdraw.crumb')}
            lead={t('settings.withdraw.lead')}
            crumbs={[
                { label: t('settings.title'), href: '/settings' },
                { label: t('settings.withdraw.crumb') },
            ]}
        >
            <Alert severity="warning">
                {t('settings.withdraw.warning', { days: graceDays })}
            </Alert>

            <SectionTitle>{t('settings.withdraw.account.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ px: 2, py: 1.5 }}>
                <Typography sx={{ fontSize: '0.9375rem' }}>{email ?? t('settings.withdraw.account.email_unset')}</Typography>
            </Paper>

            <SectionTitle note={t('settings.withdraw.services.count', { count: services.length })}>{t('settings.withdraw.services.heading')}</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<RowDivider />}>
                    {services.length === 0 && (
                        <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            {t('settings.withdraw.services.empty')}
                        </Typography>
                    )}

                    {services.map((service) => (
                        <WithdrawServiceRow
                            key={service.id}
                            service={service}
                            choice={choiceOf(service)}
                            onChange={(choice) => setData('services', { ...data.services, [service.id]: choice })}
                            credentials={credentials}
                            check={checks === undefined ? undefined : (checks[service.id] ?? null)}
                            error={fieldErrors[`services.${service.id}`]}
                        />
                    ))}
                </Stack>
            </Paper>
            {services.length > 0 && (
                <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.secondary' }}>
                    {t('settings.withdraw.services.note')}
                </Typography>
            )}

            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={data.understood}
                                onChange={(e) => setData('understood', e.target.checked)}
                            />
                        }
                        label={t('settings.withdraw.understood', { days: graceDays })}
                    />

                    {errors.understood && <Alert severity="error">{errors.understood}</Alert>}
                    {fieldErrors.services && <Alert severity="error">{fieldErrors.services}</Alert>}

                    <Stack direction="row" spacing={1.5}>
                        <Button type="submit" variant="contained" color="error" disabled={processing || !data.understood || !ready}>
                            {t('settings.withdraw.submit')}
                        </Button>
                        <Button component={InertiaLink} href="/settings" variant="outlined" color="inherit">
                            {t('settings.common.cancel')}
                        </Button>
                    </Stack>
                </Stack>
            </Box>
        </AppLayout>
    );
}

/**
 * 削除を依頼できないサービスは解放するしかないので、最初から解放にしておく。
 * 持っていける認証方法は既定ですべて選ぶ。外したい人だけ外す。
 *
 * @param service
 * @param credentials 持っていける認証方法
 */
function initialChoice(service: WithdrawalService, credentials: SplitOption[]): WithdrawalChoice {
    return { action: service.deletable ? '' : 'release', credentials: credentials.map((option) => option.id) };
}

/**
 * @param service
 * @param choice
 * @param credentials 持っていける認証方法
 * @param check 削除の問い合わせ結果
 * @returns 送れる選択になっているか
 */
function isDecided(service: WithdrawalService, choice: WithdrawalChoice, credentials: SplitOption[], check: DeletionCheck | null | undefined): boolean {
    if (choice.action === 'delete') return service.deletable && (check == null || check.accepted);
    if (choice.action === 'release') return credentials.length > 0 && choice.credentials.length > 0;

    return false;
}
