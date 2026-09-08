import { router, usePage } from '@inertiajs/react';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';

/**
 * 設定画面の切り替え。
 *
 * 今は項目が少ないのでタブにしている。監査ログや通知設定が増えて
 * 横に溢れるようになったら、左サイドナビへ移す想定 (prototype/design/D 参照)。
 */
const ITEMS = [
    { href: '/settings', label: 'プロフィール' },
    { href: '/settings/security', label: 'セキュリティ' },
] as const;

interface SettingsTabsProps {
    /** 今いるページの href */
    current: string;
}

export default function SettingsTabs({ current }: SettingsTabsProps) {
    const { isAdmin } = usePage().props;

    const items = isAdmin
        ? [...ITEMS, { href: '/admin/clients', label: '接続サービス' }]
        : [...ITEMS];

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
