import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AppLayout from '../../../Components/AppLayout';
import RowDivider from '../../../Components/RowDivider';
import SectionTitle from '../../../Components/SectionTitle';
import ToggleSwitch from '../../../Components/ToggleSwitch';
import { useConfirm } from '../../../lib/confirm';
import { t } from '../../../lib/i18n';

interface InstalledPlugin {
    /** フォルダ名 */
    name: string;
    title: string;
    description: string | null;
    version: string;
    enabled: boolean;
}

interface IndexProps {
    plugins: InstalledPlugin[];
}

/**
 * プラグインの有効と無効。plugin.json の enabled を書き換える。
 *
 * 止めるときだけ確かめる。止めるとそのプラグインの画面やログイン手段が、使っている人の前から消えるため。
 */
export default function Index({ plugins }: IndexProps) {
    const { ask, dialog } = useConfirm();

    const toggle = (plugin: InstalledPlugin, enabled: boolean): void => {
        const send = (): void => router.post(`/admin/plugins/${plugin.name}`, { enabled }, { preserveScroll: true });
        if (enabled) return send();

        ask({
            title: t('admin.plugins.disable.title', { name: plugin.title }),
            description: t('admin.plugins.disable.description'),
            confirmText: t('admin.plugins.disable.confirm'),
            onConfirm: send,
        });
    };

    return (
        <AppLayout
            title={t('admin.plugins.title')}
            lead={t('admin.plugins.lead')}
            crumbs={[{ label: t('admin.crumb'), href: '/admin' }, { label: t('admin.plugins.crumb') }]}
        >
            <SectionTitle note={t('common.count', { count: plugins.length })}>{t('admin.plugins.heading')}</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<RowDivider />}>
                    {plugins.length === 0 && (
                        <Typography sx={{ px: 2, py: 1.5, fontSize: '0.9375rem', color: 'text.disabled' }}>
                            {t('admin.plugins.empty')}
                        </Typography>
                    )}

                    {plugins.map((plugin) => (
                        <Box key={plugin.name} sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1.5 }}>
                            <Box sx={{ minWidth: 0 }}>
                                <Typography sx={{ fontSize: '0.9375rem' }}>{plugin.title}</Typography>
                                {plugin.description !== null && (
                                    <Typography sx={{ fontSize: '0.8125rem', color: 'text.secondary' }}>{plugin.description}</Typography>
                                )}
                                <Typography component="code" sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                    {plugin.name} {plugin.version}
                                </Typography>
                            </Box>
                            <ToggleSwitch
                                checked={plugin.enabled}
                                onChange={(checked) => toggle(plugin, checked)}
                                label={t('admin.plugins.toggle', { name: plugin.title })}
                            />
                        </Box>
                    ))}
                </Stack>
            </Paper>
            {dialog}
        </AppLayout>
    );
}
