import { router, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import IconButton from '@mui/material/IconButton';
import Menu from '@mui/material/Menu';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { useTheme } from '@mui/material/styles';
import Icon from './Icon';
import InertiaLink from './InertiaLink';
import MenuLink from './MenuLink';
import NavLink from './NavLink';
import ToggleSwitch from './ToggleSwitch';
import { headerBackground } from '../theme';
import { useThemeModeContext } from '../lib/theme-mode';

/**
 * 全ページ共通のヘッダー。
 *
 * 構成は DokuFarm (ロゴ / ナビ / アカウントメニュー) に合わせ、
 * 半透明 + blur で追従させるところは ModParks の AppBar に合わせている。
 *
 * ログインしていないときは、行き先の無いリンクを出さずロゴと表示設定だけにする。
 */
export default function AppHeader() {
    const { isAdmin, isLoggedIn } = usePage().props;
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
                    {isLoggedIn ? (
                        <NavLink href="/">アカウント</NavLink>
                    ) : (
                        <NavLink href="/login">ログイン</NavLink>
                    )}

                    <IconButton
                        aria-label="メニュー"
                        onClick={(event) => setAnchor(event.currentTarget)}
                        sx={{ color: 'text.secondary' }}
                    >
                        {/* ログイン前後で絵を変えない。DokuFarm も同じ1つで通している */}
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
                        {isLoggedIn && (
                            <MenuLink label="設定" onClick={() => go('/settings')} />
                        )}

                        {/* 運営としての操作。利用者自身の設定とは別物なので名前で区別する */}
                        {isAdmin && <MenuLink label="システム管理" onClick={() => go('/admin')} />}

                        {isLoggedIn && <Divider />}

                        {/* メニューを閉じずに切り替えたいので MenuItem にはしない */}
                        <Box
                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 2, px: 2, py: 1 }}
                        >
                            <Typography sx={{ fontSize: '1rem' }}>ダークテーマ</Typography>
                            <ToggleSwitch checked={mode === 'dark'} onChange={toggle} label="ダークテーマ" />
                        </Box>

                        <Divider />

                        {isLoggedIn ? (
                            <MenuLink
                                label="ログアウト"
                                onClick={() => { setAnchor(null); router.post('/logout'); }}
                            />
                        ) : (
                            <MenuLink label="ログイン" onClick={() => go('/login')} />
                        )}
                    </Menu>
                </Stack>
            </Container>
        </Box>
    );
}
