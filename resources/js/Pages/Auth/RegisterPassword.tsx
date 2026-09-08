import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';

interface RegisterPasswordProps {
    /** メールに載せた平文トークン。そのまま送り返す */
    token: string;
}

export default function RegisterPassword({ token }: RegisterPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        password: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/register/complete');
    };

    return (
        <AuthLayout title="パスワードを決める">
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.token && <Alert severity="error">{errors.token}</Alert>}

                    <Typography variant="body2" color="text.secondary">
                        メールアドレスを確認できました。パスワードを決めるとアカウントが作られます。
                    </Typography>

                    <TextField
                        label="パスワード"
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
                        アカウントを作成する
                    </Button>

                    <Typography variant="body2" color="text.secondary">
                        表示名はあとから設定できます。
                    </Typography>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
