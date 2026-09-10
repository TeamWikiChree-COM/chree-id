import { useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';

/** ログイン中のアカウント。していなければ null */
export interface SignedInAccount {
    id: string;
    email: string | null;
    displayName: string | null;
}

/** 寄せ元から持っていける認証手段 */
export interface TransferableCredential {
    id: string;
    type: string;
}

/** 画面に出す名前。移せないものはサーバ側で候補から外れている */
const CREDENTIAL_LABELS: Record<string, string> = {
    password: 'パスワード',
    totp: '認証アプリ (2段階認証)',
    magic_link: 'メールでログイン',
    oauth: 'Google 連携',
};

interface MergePanelProps {
    /** サービスから渡された平文トークン。そのまま送り返す */
    token: string;
    /** 寄せ元のサービス名 */
    serviceName: string;
    /** ログイン中のアカウント */
    signedInAs: SignedInAccount | null;
    /** 持っていける認証手段。既定は全部オン */
    transferable: TransferableCredential[];
}

/**
 * 既に持っている ChreeID へ寄せる側の画面。
 *
 * 寄せ先が本人のものだという証明は、ここでログインしていること自体で足りる。
 * メールアドレスが一致しているかは問わない。
 */
export default function MergePanel({ token, serviceName, signedInAs, transferable }: MergePanelProps) {
    const { data, setData, post, processing, errors } = useForm<{ token: string; credentials: string[] }>({
        token,
        credentials: transferable.map((credential) => credential.id),
    });

    const toggle = (id: string): void => {
        setData(
            'credentials',
            data.credentials.includes(id)
                ? data.credentials.filter((chosen) => chosen !== id)
                : [...data.credentials, id],
        );
    };

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

                {transferable.length > 0 && (
                    <Box sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, p: 2 }}>
                        <Typography variant="body2" sx={{ mb: 1 }}>
                            引き継ぐ認証方法を選んでください
                        </Typography>
                        {transferable.map((credential) => (
                            <FormControlLabel
                                key={credential.id}
                                control={
                                    <Checkbox
                                        checked={data.credentials.includes(credential.id)}
                                        onChange={() => toggle(credential.id)}
                                    />
                                }
                                label={CREDENTIAL_LABELS[credential.type] ?? credential.type}
                                sx={{ display: 'flex' }}
                            />
                        ))}
                        <Typography variant="body2" color="text.secondary">
                            外したものは使えなくなります。パスキーは引き継げないので、必要ならこの ChreeID で登録し直してください。
                        </Typography>
                    </Box>
                )}

                {errors.token && <Alert severity="error">{errors.token}</Alert>}

                <Button type="submit" variant="contained" disabled={processing}>
                    この ChreeID に追加する
                </Button>
            </Stack>
        </Box>
    );
}
