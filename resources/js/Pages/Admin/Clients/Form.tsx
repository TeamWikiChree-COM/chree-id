import { Head, router, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import FormControlLabel from '@mui/material/FormControlLabel';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import type { OAuthClient } from '../../../types';

interface TrustOption {
    value: string;
    label: string;
}

interface FormProps {
    /** 新規登録なら null */
    client: OAuthClient | null;
    trustOptions: TrustOption[];
}

export default function Form({ client, trustOptions }: FormProps) {
    const isNew = client === null;

    // trust は select の値なので string で持つ。妥当性はサーバ側 (ServiceTrust) で確かめる
    const { data, setData, post, processing, errors, transform } = useForm<{
        name: string;
        redirect_uris: string;
        scopes: string;
        trust: string;
        is_confidential: boolean;
    }>({
        name: client?.name ?? '',
        // 1行1つで編集させる。配列を UI に出すより間違いが起きにくい
        redirect_uris: (client?.redirectUris ?? []).join('\n'),
        scopes: client?.scopes ?? 'openid profile email',
        trust: client?.trust ?? 'unapproved',
        is_confidential: client?.isConfidential ?? true,
    });

    // 画面では1行1つ、サーバへは配列で渡す
    transform((form) => ({
        ...form,
        redirect_uris: form.redirect_uris
            .split(/\r?\n/)
            .map((uri) => uri.trim())
            .filter((uri) => uri !== ''),
    }));

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        post(isNew ? '/admin/clients' : `/admin/clients/${client.id}`);
    };

    const remove = (): void => {
        if (client === null) return;
        if (!window.confirm(`${client.name} を削除します。このサービスからはログインできなくなります。`)) return;

        router.post(`/admin/clients/${client.id}/delete`);
    };

    const rotate = (): void => {
        if (client === null) return;
        if (!window.confirm('client_secret を作り直します。RP 側の設定を書き換えるまでログインが止まります。')) return;

        router.post(`/admin/clients/${client.id}/secret`);
    };

    return (
        <>
            <Head title={isNew ? 'サービスを登録' : 'サービスの設定'} />

            <Container maxWidth="sm" sx={{ py: 6 }}>
                <Stack spacing={3}>
                    <Typography variant="h6" component="h1">
                        {isNew ? 'サービスを登録' : client.name}
                    </Typography>

                    {!isNew && (
                        <Alert severity="info">
                            <Box component="code" sx={{ wordBreak: 'break-all' }}>{client.id}</Box>
                        </Alert>
                    )}

                    <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                        <Box component="form" onSubmit={submit} noValidate>
                            <Stack spacing={2}>
                                <TextField
                                    label="サービス名"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    error={Boolean(errors.name)}
                                    helperText={errors.name ?? '同意画面に出ます'}
                                    autoFocus
                                    required
                                />

                                <TextField
                                    label="リダイレクト先"
                                    value={data.redirect_uris}
                                    onChange={(e) => setData('redirect_uris', e.target.value)}
                                    error={Boolean(errors.redirect_uris)}
                                    helperText={errors.redirect_uris ?? '1行に1つ。完全一致で照合します'}
                                    multiline
                                    minRows={3}
                                    required
                                />

                                <TextField
                                    label="スコープ"
                                    value={data.scopes}
                                    onChange={(e) => setData('scopes', e.target.value)}
                                    error={Boolean(errors.scopes)}
                                    helperText={errors.scopes ?? '空白区切り'}
                                    required
                                />

                                <TextField
                                    label="信頼状態"
                                    value={data.trust}
                                    onChange={(e) => setData('trust', e.target.value)}
                                    error={Boolean(errors.trust)}
                                    helperText={errors.trust}
                                    select
                                >
                                    {trustOptions.map((option) => (
                                        <MenuItem key={option.value} value={option.value}>
                                            {option.label}
                                        </MenuItem>
                                    ))}
                                </TextField>

                                {isNew && (
                                    <FormControlLabel
                                        control={
                                            <Switch
                                                checked={data.is_confidential}
                                                onChange={(e) => setData('is_confidential', e.target.checked)}
                                            />
                                        }
                                        label="client_secret を発行する"
                                    />
                                )}

                                <Button type="submit" variant="contained" disabled={processing}>
                                    {isNew ? '登録する' : '保存する'}
                                </Button>
                            </Stack>
                        </Box>
                    </Paper>

                    {!isNew && (
                        <Paper sx={{ p: 2, border: '1px solid', borderColor: 'divider' }}>
                            <Stack spacing={1} sx={{ alignItems: 'flex-start' }}>
                                <Typography variant="subtitle2">このサービスの操作</Typography>

                                {client.isConfidential && (
                                    <Button variant="outlined" color="inherit" onClick={rotate}>
                                        client_secret を作り直す
                                    </Button>
                                )}

                                <Button variant="outlined" color="error" onClick={remove}>
                                    削除する
                                </Button>
                            </Stack>
                        </Paper>
                    )}

                    <Box>
                        <Button color="inherit" onClick={() => router.get('/admin/clients')}>
                            一覧に戻る
                        </Button>
                    </Box>
                </Stack>
            </Container>
        </>
    );
}
