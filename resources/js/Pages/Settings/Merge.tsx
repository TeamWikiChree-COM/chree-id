import { useForm, usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import AppLayout from '../../Components/AppLayout';
import PasswordField from '../../Components/PasswordField';
import SectionTitle from '../../Components/SectionTitle';
import { useConfirm } from '../../lib/confirm';
import { t } from '../../lib/i18n';
import type { TranslationKey } from '../../lib/i18n';
import { methodLabel } from '../../lib/credentials';

/** 寄せ元の候補 */
interface Candidate {
    id: string;
    displayName: string | null;
    email: string | null;
    hasUserAccount: boolean;
}

/** 引き継げる認証手段 */
interface Transferable {
    id: string;
    type: string;
    label: string | null;
}

interface MergeProps {
    candidate: Candidate;
    /** 相手側の証明に使える方式。空なら統合できない */
    proofs: string[];
    transferable: Transferable[];
}

/**
 * 候補として挙がった別アカウントを、いまのアカウントへ寄せる。
 *
 * **同じメールアドレスであることは実行の根拠にならない。** 相手側の認証を
 * この画面で通してもらう。通らなければ統合しない。
 */
export default function Merge({ candidate, proofs, transferable }: MergeProps) {
    const { errors } = usePage().props;
    const { ask, dialog } = useConfirm();

    const form = useForm({
        candidate: candidate.id,
        proof: proofs[0] ?? '',
        secret: '',
        // 引き継ぐものは既定で全部オン (KAKUTEI.md)
        credentials: transferable.map((c) => c.id),
    });

    const toggle = (id: string): void => {
        form.setData(
            'credentials',
            form.data.credentials.includes(id)
                ? form.data.credentials.filter((c) => c !== id)
                : [...form.data.credentials, id],
        );
    };

    const submit = (): void => {
        ask({
            title: t('settings.merge.confirm_title'),
            description: t('settings.merge.confirm_description'),
            confirmText: t('settings.merge.confirm'),
            onConfirm: () => form.post('/settings/merge'),
        });
    };

    const name = candidate.displayName ?? candidate.email ?? candidate.id;

    return (
        <AppLayout
            title={t('settings.merge.title')}
            lead={t('settings.merge.lead')}
            crumbs={[{ label: t('settings.merge.crumb') }]}
        >
            <SectionTitle>{t('settings.merge.source_heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Typography sx={{ fontSize: '0.9375rem' }}>{name}</Typography>
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                    {t('settings.merge.source_note')}
                </Typography>
            </Paper>

            <SectionTitle>{t('settings.merge.proof_heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {proofs.length === 0 ? (
                    <Alert severity="warning">{t('settings.merge.proof.none')}</Alert>
                ) : (
                    <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            {t('settings.merge.proof_note')}
                        </Typography>

                        {proofs.length > 1 && (
                            <TextField
                                select
                                value={form.data.proof}
                                onChange={(e) => form.setData('proof', e.target.value)}
                                sx={{ minWidth: 260 }}
                            >
                                {proofs.map((proof) => (
                                    <MenuItem key={proof} value={proof}>
                                        {t(`settings.merge.proof.${proof}` as TranslationKey)}
                                    </MenuItem>
                                ))}
                            </TextField>
                        )}

                        {form.data.proof === 'password' ? (
                            <PasswordField
                                label={t('settings.merge.proof.password')}
                                value={form.data.secret}
                                onChange={(e) => form.setData('secret', e.target.value)}
                                error={Boolean(errors.secret)}
                                helperText={errors.secret}
                                autoComplete="off"
                            />
                        ) : (
                            <TextField
                                label={t('settings.merge.proof.totp')}
                                value={form.data.secret}
                                onChange={(e) => form.setData('secret', e.target.value)}
                                error={Boolean(errors.secret)}
                                helperText={errors.secret}
                                slotProps={{ htmlInput: { inputMode: 'numeric' } }}
                            />
                        )}
                    </Stack>
                )}
            </Paper>

            <SectionTitle>{t('settings.merge.transfer_heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                {transferable.length === 0 ? (
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        {t('settings.merge.transfer_empty')}
                    </Typography>
                ) : (
                    <Box>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', mb: 1 }}>
                            {t('settings.merge.transfer_note')}
                        </Typography>

                        {transferable.map((credential) => (
                            <Box key={credential.id}>
                                <FormControlLabel
                                    control={
                                        <Checkbox
                                            checked={form.data.credentials.includes(credential.id)}
                                            onChange={() => toggle(credential.id)}
                                        />
                                    }
                                    label={credential.label ?? methodLabel(credential.type)}
                                />
                            </Box>
                        ))}
                    </Box>
                )}
            </Paper>

            <Box sx={{ mt: 2 }}>
                <Button
                    variant="contained"
                    color="error"
                    disabled={proofs.length === 0 || form.processing}
                    onClick={submit}
                >
                    {t('settings.merge.submit')}
                </Button>
            </Box>

            {dialog}
        </AppLayout>
    );
}
