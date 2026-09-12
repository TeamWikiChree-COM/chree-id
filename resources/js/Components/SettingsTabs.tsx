import { router } from '@inertiajs/react';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';

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
const ITEMS = [
    { href: '/settings', label: 'プロフィール' },
    { href: '/settings/security', label: 'セキュリティ' },
    { href: '/settings/connections', label: '外部アカウント' },
    { href: '/settings/devices', label: '端末' },
] as const;

interface SettingsTabsProps {
    /** 今いるページの href */
    current: string;
}

export default function SettingsTabs({ current }: SettingsTabsProps) {
    return (
        <Tabs
            value={current}
            onChange={(_, href: string) => router.get(href)}
            sx={{ mb: 1, borderBottom: '1px solid', borderColor: 'divider' }}
        >
            {ITEMS.map((item) => (
                <Tab key={item.href} value={item.href} label={item.label} />
            ))}
        </Tabs>
    );
}
