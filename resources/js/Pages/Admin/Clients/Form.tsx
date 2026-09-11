import { router, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import FormControlLabel from '@mui/material/FormControlLabel';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import type { FormEvent } from 'react';
import AppLayout from '../../../Components/AppLayout';
import Icon from '../../../Components/Icon';
import SectionTitle from '../../../Components/SectionTitle';
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
        skips_consent: boolean;
        can_provision: boolean;
        is_confidential: boolean;
    }>({
        name: client?.name ?? '',
        // 1行1つで編集させる。配列を UI に出すより間違いが起きにくい
        redirect_uris: (client?.redirectUris ?? []).join('\n'),
        scopes: client?.scopes ?? 'openid profile email',
        trust: client?.trust ?? 'unapproved',
        skips_consent: client?.skipsConsent ?? false,
        can_provision: client?.canProvision ?? false,
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
        <AppLayout
            title={isNew ? 'サービスを登録' : client.name}
            crumbs={[
                { label: 'ChreeID', href: '/' },
                { label: 'システム管理', href: '/admin' },
                { label: '接続サービス', href: '/admin/clients' },
                { label: isNew ? '登録' : '設定' },
            ]}
        >
            {!isNew && (
                <Alert severity="info" sx={{ mb: 2 }}>
                    <Box component="code" sx={{ wordBreak: 'break-all' }}>{client.id}</Box>
                </Alert>
            )}

            <Paper variant="outlined" sx={{ p: 2 }}>
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

                        <FormControlLabel
                            control={
                                <Switch
                                    checked={data.skips_consent}
                                    onChange={(e) => setData('skips_consent', e.target.checked)}
                                />
                            }
                            label="同意画面を省略する"
                        />

                        <FormControlLabel
                            control={
                                <Switch
                                    checked={data.can_provision}
                                    onChange={(e) => setData('can_provision', e.target.checked)}
                                />
                            }
                            label="サービスアカウントを扱える (遅延登録・移行)"
                        />

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

                        <Box>
                            <Button type="submit" variant="contained" disabled={processing}>
                                {isNew ? '登録する' : '保存する'}
                            </Button>
                        </Box>
                    </Stack>
                </Box>
            </Paper>

            {!isNew && (
                <>
                    <SectionTitle>このサービスの操作</SectionTitle>
                    <Paper variant="outlined" sx={{ p: 2 }}>
                        <Stack spacing={1.5} sx={{ alignItems: 'flex-start' }}>
                            {client.isConfidential && (
                                <Button variant="outlined" color="inherit" startIcon={<Icon name="rotate" />} onClick={rotate}>
                                    client_secret を作り直す
                                </Button>
                            )}
                            <Button variant="outlined" color="error" startIcon={<Icon name="trash" />} onClick={remove}>
                                削除する
                            </Button>
                        </Stack>
                    </Paper>
                </>
            )}
        </AppLayout>
    );
}
