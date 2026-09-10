import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';

/** ログイン中のアカウント。していなければ null */
export interface SignedInAccount {
    id: string;
    email: string | null;
    displayName: string | null;
}

interface MergePanelProps {
    /** サービスから渡された平文トークン。そのまま送り返す */
    token: string;
    /** 寄せ元のサービス名 */
    serviceName: string;
    /** ログイン中のアカウント */
    signedInAs: SignedInAccount | null;
}

/**
 * 既に持っている ChreeID へ寄せる側の画面。
 *
 * 寄せ先が本人のものだという証明は、ここでログインしていること自体で足りる。
 * メールアドレスが一致しているかは問わない。
 */
export default function MergePanel({ token, serviceName, signedInAs }: MergePanelProps) {
    const { post, processing, errors } = useForm({ token });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post('/claim/merge');
    };

    if (signedInAs === null) {
        return (
            <Stack spacing={2}>
                <Typography variant="body2" color="text.secondary">
                    お使いの ChreeID にログインすると、{serviceName}
                    のアカウントをそこに追加できます。ログインが済むとこの画面に戻ります。
                </Typography>
                <Button component="a" href="/login" variant="contained">
                    ChreeID にログインする
                </Button>
            </Stack>
        );
    }

    return (
        <Box component="form" onSubmit={submit} noValidate>
            <Stack spacing={2}>
                <Box sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, p: 2 }}>
                    <Typography variant="body2" color="text.secondary">
                        ログイン中
                    </Typography>
                    <Typography>{signedInAs.displayName ?? signedInAs.email ?? signedInAs.id}</Typography>
                    {signedInAs.displayName !== null && signedInAs.email !== null && (
                        <Typography variant="body2" color="text.secondary">
                            {signedInAs.email}
                        </Typography>
                    )}
                </Box>

                <Typography variant="body2" color="text.secondary">
                    {serviceName}
                    でお使いのアカウントを、この ChreeID に追加します。これまでの利用状況はそのまま引き継がれます。
                </Typography>

                {errors.token && <Alert severity="error">{errors.token}</Alert>}

                <Button type="submit" variant="contained" disabled={processing}>
                    この ChreeID に追加する
                </Button>
            </Stack>
        </Box>
    );
}
