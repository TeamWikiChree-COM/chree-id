import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import AppLayout from '../../Components/AppLayout';
import NavRow from '../../Components/NavRow';
import SectionTitle from '../../Components/SectionTitle';
import StatCard from '../../Components/StatCard';

interface AdminIndexProps {
    stats: {
        clients: number;
        accounts: number;
        suspended: number;
    };
    /** まだ適用されていないマイグレーションの数 */
    pendingMigrations: number;
}

export default function AdminIndex({ stats, pendingMigrations }: AdminIndexProps) {
    return (
        <AppLayout
            title="システム管理"
            lead="運営としての操作です。自分のアカウントの設定は「設定」にあります"
            crumbs={[{ label: 'ChreeID', href: '/' }, { label: 'システム管理' }]}
        >
            <Stack direction="row" spacing={1.5}>
                <StatCard label="接続サービス" value={stats.clients} />
                <StatCard label="アカウント" value={stats.accounts} />
                <StatCard label="停止中" value={stats.suspended} />
            </Stack>

            <SectionTitle>管理する</SectionTitle>
            <Paper variant="outlined">
                <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                    <NavRow
                        icon="users"
                        title="アカウント"
                        description="登録済みアカウントの一覧・状態の確認"
                        href="/admin/accounts"
                    />
                    <NavRow
                        icon="plug"
                        title="接続サービス"
                        description="ChreeID でログインできるサービスの登録・編集"
                        href="/admin/clients"
                    />
                    <NavRow
                        icon="database"
                        title="データベース構造"
                        description={
                            pendingMigrations === 0
                                ? '構造は最新です'
                                : `未適用が ${pendingMigrations} 件あります`
                        }
                        href="/admin/migrations"
                    />
                    <NavRow
                        icon="broom"
                        title="掃除"
                        description="期限切れの申し込みと使い捨てトークンの削除"
                        href="/admin/maintenance"
                    />
                </Stack>
            </Paper>
        </AppLayout>
    );
}
