import { router, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import IconButton from '@mui/material/IconButton';
import MenuItem from '@mui/material/MenuItem';
import Menu from '@mui/material/Menu';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { useTheme } from '@mui/material/styles';
import Icon from './Icon';
import InertiaLink from './InertiaLink';
import ToggleSwitch from './ToggleSwitch';
import { headerBackground } from '../theme';
import { useThemeModeContext } from '../lib/theme-mode';

/** ヘッダーのリンク1つ分 */
function NavLink({ href, children }: { href: string; children: string }) {
    return (
        <Typography
            component={InertiaLink}
            href={href}
            sx={{
                px: 1.5,
                py: 0.75,
                fontSize: '0.875rem',
                color: 'text.secondary',
                textDecoration: 'none',
                borderRadius: 1,
                '&:hover': { bgcolor: 'action.hover', color: 'text.primary' },
            }}
        >
            {children}
        </Typography>
    );
}

/**
 * 全ページ共通のヘッダー。
 *
 * 構成は DokuFarm (ロゴ / ナビ / アカウントメニュー) に合わせ、
 * 半透明 + blur で追従させるところは ModParks の AppBar に合わせている。
 */
export default function AppHeader() {
    const { isAdmin } = usePage().props;
    const { mode, toggle } = useThemeModeContext();
    const theme = useTheme();
    const [anchor, setAnchor] = useState<HTMLElement | null>(null);

    const go = (href: string): void => {
        setAnchor(null);
        router.get(href);
    };

    return (
        <Box
            component="header"
            sx={{
                position: 'sticky',
                top: 0,
                zIndex: theme.zIndex.appBar,
                py: 1,
                bgcolor: headerBackground(mode),
                backdropFilter: 'blur(8px)',
                borderBottom: '1px solid',
                borderColor: 'divider',
            }}
        >
            <Container maxWidth="md" sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Box
                    component={InertiaLink}
                    href="/"
                    sx={{ display: 'inline-flex', alignItems: 'center', gap: 1, textDecoration: 'none' }}
                >
                    <Box component="img" src="/icon.png" alt="" sx={{ width: 28, height: 28 }} />
                    <Typography sx={{ fontSize: '1.25rem', fontWeight: 700, color: 'text.primary', lineHeight: 1 }}>
                        ChreeID
                    </Typography>
                </Box>

                <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                    <NavLink href="/">アカウント</NavLink>
                    <NavLink href="/settings">設定</NavLink>

                    <IconButton
                        aria-label="アカウントメニュー"
                        onClick={(event) => setAnchor(event.currentTarget)}
                        sx={{ color: 'text.secondary' }}
                    >
                        <Icon name="circle-user" />
                    </IconButton>

                    <Menu
                        anchorEl={anchor}
                        open={anchor !== null}
                        onClose={() => setAnchor(null)}
                        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
                        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
                        slotProps={{ paper: { variant: 'outlined', sx: { minWidth: 230, mt: 0.5 } } }}
                    >
                        <MenuItem onClick={() => go('/settings')}>
                            <Icon name="user" sx={{ width: 20, mr: 1, fontSize: '0.875rem' }} />
                            プロフィール
                        </MenuItem>
                        <MenuItem onClick={() => go('/settings/security')}>
                            <Icon name="shield-halved" sx={{ width: 20, mr: 1, fontSize: '0.875rem' }} />
                            セキュリティ
                        </MenuItem>
                        {isAdmin && (
                            <MenuItem onClick={() => go('/admin/clients')}>
                                <Icon name="plug" sx={{ width: 20, mr: 1, fontSize: '0.875rem' }} />
                                接続サービス
                            </MenuItem>
                        )}

                        <Divider />

                        {/* メニューを閉じずに切り替えたいので MenuItem にはしない */}
                        <Box
                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1 }}
                        >
                            <Typography sx={{ fontSize: '0.875rem' }}>ダークテーマ</Typography>
                            <ToggleSwitch checked={mode === 'dark'} onChange={toggle} label="ダークテーマ" />
                        </Box>

                        <Divider />

                        <MenuItem onClick={() => { setAnchor(null); router.post('/logout'); }}>
                            <Icon name="arrow-right-from-bracket" sx={{ width: 20, mr: 1, fontSize: '0.875rem' }} />
                            ログアウト
                        </MenuItem>
                    </Menu>
                </Stack>
            </Container>
        </Box>
    );
}
