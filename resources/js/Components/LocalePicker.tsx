import { router, usePage } from '@inertiajs/react';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import Typography from '@mui/material/Typography';
import { localeLabel, t } from '../lib/i18n';

/**
 * 表示言語の切り替え。
 *
 * **選ばないままなら、既定はブラウザの言語設定。** ここで選んだときだけ
 * サーバに覚えさせる (ログイン中は本人の行、未ログインは Cookie)。
 *
 * 反映はサーバの再描画で行う。辞書はバンドルに畳み込まれているが、
 * バリデーションやメールの文言はサーバ側にあるので、両方を1回で揃える。
 */
export default function LocalePicker() {
    const { locale, locales } = usePage().props;

    return (
        <>
            <Typography sx={{ fontSize: '1rem' }}>{t('common.nav.language')}</Typography>
            <Select
                size="small"
                value={locale}
                onChange={(event) => router.post('/locale', { locale: event.target.value })}
                inputProps={{ 'aria-label': t('common.nav.language') }}
                sx={{ minWidth: 120 }}
            >
                {locales.map((name) => (
                    <MenuItem key={name} value={name}>
                        {localeLabel(name)}
                    </MenuItem>
                ))}
            </Select>
        </>
    );
}
