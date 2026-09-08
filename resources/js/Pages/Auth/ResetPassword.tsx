import { Link, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';

interface ResetPasswordProps {
    /** メールに載せた平文トークン。そのまま送り返す */
    token: string;
}

export default function ResetPassword({ token }: ResetPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        password: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/password/reset');
    };

    return (
        <AuthLayout
            title="新しいパスワード"
            heading="新しいパスワードを決める"
            footer={
                <Typography variant="body2">
                    <Link href="/login">ログイン画面に戻る</Link>
                </Typography>
            }
        >
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.token && <Alert severity="error">{errors.token}</Alert>}

                    <TextField
                        label="新しいパスワード"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={Boolean(errors.password)}
                        helperText={errors.password ?? '8文字以上'}
                        autoComplete="new-password"
                        autoFocus
                        required
                    />

                    <Button type="submit" variant="contained" disabled={processing}>
                        設定する
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
