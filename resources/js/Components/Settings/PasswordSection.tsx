import { useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import PasswordField from '../PasswordField';

interface PasswordSectionProps {
    /** 設定済みか。未設定なら現在のパスワードは聞かない (聞いても答えられない) */
    hasPassword: boolean;
}

/**
 * 設定画面からのパスワード変更。
 *
 * 再設定 (/password/forgot) とは裏付けが違う。あちらはメールが本人の証明、
 * こちらは「いまのパスワードを知っていること」が証明になる。
 */
export default function PasswordSection({ hasPassword }: PasswordSectionProps) {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.post('/settings/password', { onSuccess: () => form.reset() });
    };

    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                    {!hasPassword && (
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            まだ設定されていません。設定すると、メールアドレスとパスワードでログインできます
                        </Typography>
                    )}

                    {hasPassword && (
                        <PasswordField
                            label="現在のパスワード"
                            value={form.data.current_password}
                            onChange={(e) => form.setData('current_password', e.target.value)}
                            error={Boolean(form.errors.current_password)}
                            helperText={form.errors.current_password}
                            autoComplete="current-password"
                        />
                    )}

                    <PasswordField
                        label="新しいパスワード"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        error={Boolean(form.errors.password)}
                        helperText={form.errors.password ?? '8文字以上'}
                        autoComplete="new-password"
                    />

                    <PasswordField
                        label="新しいパスワード (確認)"
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                    />

                    <Button type="submit" variant="contained" disabled={form.processing}>
                        {hasPassword ? '変更する' : '設定する'}
                    </Button>
                </Stack>
            </Box>
        </Paper>
    );
}
