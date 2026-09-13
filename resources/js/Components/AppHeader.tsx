import { router, usePage } from '@inertiajs/react';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import IconButton from '@mui/material/IconButton';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { useTheme } from '@mui/material/styles';
import Icon from './Icon';
import InertiaLink from './InertiaLink';
import LocalePicker from './LocalePicker';
import MenuLink from './MenuLink';
import NavLink from './NavLink';
import ToggleSwitch from './ToggleSwitch';
import { headerBackground } from '../theme';
import { useThemeModeContext } from '../lib/theme-mode';
import { t } from '../lib/i18n';

/**
 * 全ページ共通のヘッダー。
 *
 * 構成は DokuFarm (ロゴ / ナビ / アカウントメニュー) に合わせ、
 * 半透明 + blur で追従させるところは ModParks の AppBar に合わせている。
 *
 * ログインしていないときは、行き先の無いリンクを出さずロゴと表示設定だけにする。
 */
export default function AppHeader() {
    const { isAdmin, isLoggedIn, iconUrl, appName, appLogoUrl } = usePage().props;
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
                    <Box component="img" src={appLogoUrl} alt="" sx={{ width: 28, height: 28 }} />
                    <Typography sx={{ fontSize: '1.25rem', fontWeight: 700, color: 'text.primary', lineHeight: 1 }}>
                        {appName}
                    </Typography>
                </Box>

                <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                    {isLoggedIn ? (
                        <NavLink href="/">{t('common.nav.account')}</NavLink>
                    ) : (
                        <NavLink href="/login">{t('common.nav.login')}</NavLink>
                    )}

                    <IconButton
                        aria-label={t('common.nav.menu')}
                        onClick={(event) => setAnchor(event.currentTarget)}
                        sx={{ color: 'text.secondary' }}
                    >
                        {/* アイコンを設定していれば本人の絵。無ければログイン前後で変えない */}
                        {iconUrl === null ? (
                            <Icon name="circle-user" />
                        ) : (
                            <Avatar src={iconUrl} sx={{ width: 24, height: 24 }} />
                        )}
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
                            <MenuLink label={t('common.nav.settings')} onClick={() => go('/settings')} />
                        )}

                        {/* 運営としての操作。利用者自身の設定とは別物なので名前で区別する */}
                        {isLoggedIn && (
                            <MenuLink label={t('services.title')} onClick={() => go('/services')} />
                        )}

                        {isAdmin && <MenuLink label={t('common.nav.admin')} onClick={() => go('/admin')} />}

                        {isLoggedIn && <Divider sx={{ my: 1 }} />}

                        {/* メニューを閉じずに切り替えたいので component="div" にする */}
                        <MenuItem
                            component="div"
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 2,
                                cursor: 'default',
                                '&:hover': { bgcolor: 'transparent' },
                            }}
                        >
                            <Typography sx={{ fontSize: '1rem' }}>{t('common.nav.dark_theme')}</Typography>
                            <ToggleSwitch checked={mode === 'dark'} onChange={toggle} label={t('common.nav.dark_theme')} />
                        </MenuItem>

                        <MenuItem
                            component="div"
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 2,
                                cursor: 'default',
                                '&:hover': { bgcolor: 'transparent' },
                            }}
                        >
                            <LocalePicker />
                        </MenuItem>

                        <Divider sx={{ my: 1 }} />

                        {isLoggedIn ? (
                            <MenuLink
                                label={t('common.nav.logout')}
                                onClick={() => { setAnchor(null); router.post('/logout'); }}
                            />
                        ) : (
                            <MenuLink label={t('common.nav.login')} onClick={() => go('/login')} />
                        )}
                    </Menu>
                </Stack>
            </Container>
        </Box>
    );
}
