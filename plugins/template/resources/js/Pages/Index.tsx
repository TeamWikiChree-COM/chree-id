import Typography from '@mui/material/Typography';
import AppLayout from '@/Components/AppLayout';
import { t as core } from '@/lib/i18n';
import { t } from '../lib/i18n';

interface IndexProps {
    greeting: string;
}

/**
 * 本体の部品 (@/Components/…) はそのまま使ってよい。
 */
const Index = ({ greeting }: IndexProps) => {
    return (
        <AppLayout
            title={t('page.title')}
            lead={t('page.lead')}
            crumbs={[{ label: core('dashboard.crumb'), href: '/' }, { label: t('page.title') }]}
        >
            <Typography>{greeting}</Typography>
        </AppLayout>
    );
};

export default Index;