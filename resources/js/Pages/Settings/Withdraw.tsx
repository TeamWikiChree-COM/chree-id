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
import SectionTitle from '../../Components/SectionTitle';
import ServiceIcon from '../../Components/ServiceIcon';
import type { ConnectedService } from '../../types';

interface WithdrawProps {
    email: string | null;
    /** 連携中のサービス。退会すると一緒に使えなくなる */
    services: ConnectedService[];
    /** 消えるまでの日数 */
    graceDays: number;
}

/**
 * ChreeID の退会。
 *
 * 何を失うのかを先に見せる。連携しているサービスのアカウントも
 * 一緒に使えなくなるので、そこを伏せたまま押させない。
 */
export default function Withdraw({ email, services, graceDays }: WithdrawProps) {
    const { data, setData, post, processing, errors } = useForm({ understood: false });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/settings/withdraw');
    };

    return (
        <AppLayout
            title="退会"
            lead="ChreeID アカウントを削除します"
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: '設定', href: '/settings' },
                { label: '退会' },
            ]}
        >
            <Alert severity="warning">
                退会すると、すぐにログインできなくなります。{graceDays} 日以内であれば元に戻せますが、
                それを過ぎるとアカウントと認証情報は完全に削除され、元に戻せません
            </Alert>

            <SectionTitle>退会するアカウント</SectionTitle>
            <Paper variant="outlined" sx={{ px: 2, py: 1.5 }}>
                <Typography sx={{ fontSize: '0.9375rem' }}>{email ?? 'メールアドレス未設定'}</Typography>
            </Paper>

            <SectionTitle note={`${services.length}件`}>一緒に使えなくなるサービス</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    {services.length === 0 && (
                        <Typography sx={{ px: 2, py: 2, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            連携しているサービスはありません
                        </Typography>
                    )}

                    {services.map((service) => (
                        <Box key={service.id} sx={{ display: 'flex', alignItems: 'center', gap: 1.5, px: 2, py: 1.5 }}>
                            <ServiceIcon name={service.name} iconUrl={service.iconUrl} />
                            <Typography sx={{ fontSize: '0.9375rem' }}>{service.name}</Typography>
                        </Box>
                    ))}
                </Stack>
            </Paper>

            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                    <FormControlLabel
                        control={
                            <Checkbox
                                checked={data.understood}
                                onChange={(e) => setData('understood', e.target.checked)}
                            />
                        }
                        label={`${String(graceDays)} 日を過ぎると元に戻せないことを理解しました`}
                    />

                    {errors.understood && <Alert severity="error">{errors.understood}</Alert>}

                    <Stack direction="row" spacing={1.5}>
                        <Button type="submit" variant="contained" color="error" disabled={processing || !data.understood}>
                            退会する
                        </Button>
                        <Button component={InertiaLink} href="/settings" variant="outlined" color="inherit">
                            やめる
                        </Button>
                    </Stack>
                </Stack>
            </Box>
        </AppLayout>
    );
}
