import { router } from '@inertiajs/react';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import { t } from '../lib/i18n';

/**
 * 設定画面の切り替え。
 *
 * 今は項目が少ないのでタブにしている。監査ログや通知設定が増えて
 * 横に溢れるようになったら、左サイドナビへ移す想定 (prototype/design/D 参照)。
 *
 * ここに並べるのは利用者自身の設定だけ。運営としての操作 (接続サービスの登録など) は
 * /admin に置く。同じ画面に混ぜると、自分の設定を触っているつもりで
 * システム全体を変えてしまう。
 */
interface SettingsTabsProps {
    /** 今いるページの href */
    current: string;
}

export default function SettingsTabs({ current }: SettingsTabsProps) {
    const items = [
        { href: '/settings', label: t('settings.profile.crumb') },
        { href: '/settings/security', label: t('settings.security.crumb') },
        { href: '/settings/connections', label: t('settings.connections.crumb') },
        { href: '/settings/devices', label: t('settings.devices.crumb') },
        { href: '/settings/activity', label: t('settings.activity.crumb') },
    ] as const;

    return (
        <Tabs
            value={current}
            onChange={(_, href: string) => router.get(href)}
            sx={{ mb: 1, borderBottom: '1px solid', borderColor: 'divider' }}
        >
            {items.map((item) => (
                <Tab key={item.href} value={item.href} label={item.label} />
            ))}
        </Tabs>
    );
}
