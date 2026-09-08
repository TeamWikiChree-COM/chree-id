import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AuthLayout from '../../Components/AuthLayout';

interface ClaimShowProps {
    /** サービスから渡された平文トークン。そのまま送り返す */
    token: string;
    /** 引き取り元のサービス名 */
    serviceName: string;
    /** 既に分かっている連絡先 */
    email: string | null;
    /** その連絡先の到達性が確認済みか */
    emailVerified: boolean;
    /** 既に分かっている表示名 */
    displayName: string | null;
}

/**
 * 引き取り (claim) の画面。
 *
 * 利用者から見れば新規作成だが、実際にはサービス利用時に裏で用意された
 * アカウントを自分のものにする操作。そこは説明せず、結果だけ伝える。
 */
export default function ClaimShow({ token, serviceName, email, emailVerified, displayName }: ClaimShowProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        password: '',
        display_name: displayName ?? '',
        email: email ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/claim');
    };

    return (
        <AuthLayout title="ChreeID を作成" heading="ChreeID を作成">
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2}>
                    <Typography variant="body2" color="text.secondary">
                        {serviceName}
                        でお使いのアカウントを、WikiChree.COM 共通の ChreeID として使えるようにします。
                        これまでの利用状況はそのまま引き継がれます。
                    </Typography>

                    {email !== null && emailVerified ? (
                        <TextField label="メールアドレス" value={email} slotProps={{ input: { readOnly: true } }} />
                    ) : (
                        <TextField
                            label="メールアドレス"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={Boolean(errors.email)}
                            helperText={errors.email ?? '確認のメールをお送りします'}
                            autoComplete="email"
                            required
                        />
                    )}

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

                    <TextField
                        label="表示名"
                        value={data.display_name}
                        onChange={(e) => setData('display_name', e.target.value)}
                        error={Boolean(errors.display_name)}
                        helperText={errors.display_name ?? '任意。あとから変更できます'}
                        autoComplete="nickname"
                    />

                    {errors.token && <Alert severity="error">{errors.token}</Alert>}

                    <Button type="submit" variant="contained" disabled={processing}>
                        ChreeID を作成する
                    </Button>
                </Stack>
            </Box>
        </AuthLayout>
    );
}
