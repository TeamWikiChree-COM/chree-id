import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';
import PasswordField from '../../Components/PasswordField';

interface RegisterPasswordProps {
    /** メールに載せた平文トークン。そのまま送り返す */
    token: string;
}

export default function RegisterPassword({ token }: RegisterPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        password: '',
        display_name: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/register/complete');
    };

    return (
        <AuthLayout title="アカウントの作成"
            heading="パスワードを決める">
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    {errors.token && <Alert severity="error">{errors.token}</Alert>}

                    <Typography variant="body2" color="text.secondary">
                        メールアドレスを確認できました。パスワードを決めるとアカウントが作られます。
                    </Typography>

                    <PasswordField
                        label="パスワード"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={Boolean(errors.password)}
                        helperText={errors.password ?? '8文字以上'}
                        autoComplete="new-password"
                        autoFocus
                        required
                    />

                    <TextField
                        label="表示名"
                        value={data.display_name}
                        onChange={(e) => setData('display_name', e.target.value)}
                        error={Boolean(errors.display_name)}
                        helperText={errors.display_name ?? '任意。あとから変更できます'}
                        autoComplete="nickname"
                    />

                    <Button type="submit" variant="contained" disabled={processing}>
                        アカウントを作成する
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
