import { usePage } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import { t } from '../lib/i18n';

/**
 * 全画面の最下部に置く著作表示。
 *
 * 年は閲覧時点のものを出す。ビルド時に固定すると、年をまたいでも古いまま残る。
 */
const SiteFooter = () => {
    const { appName } = usePage().props;

    return (
        <Typography
            component="footer"
            sx={{ py: 3, textAlign: 'center', fontSize: '0.75rem', color: 'text.disabled' }}
        >
            {t('common.footer.copyright', { year: new Date().getFullYear(), app: appName })}
        </Typography>
    );
};

export default SiteFooter;
