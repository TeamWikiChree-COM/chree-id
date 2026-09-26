import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { t } from '../../lib/i18n';
import type { ServiceUrls } from '../../types';

interface ServiceUrlFieldsProps {
    data: ServiceUrls;
    errors: Partial<Record<keyof ServiceUrls, string>>;
    /** 変わった項目だけを渡す */
    onChange: (changed: Partial<ServiceUrls>) => void;
}

/**
 * サービスの URL 群 (戻り先・設定画面・アイコン) の入力欄。
 *
 * 利用者の登録画面と管理画面で同じものを使い、入力の仕方を揃える。
 */
const ServiceUrlFields = ({ data, errors, onChange }: ServiceUrlFieldsProps) => {
    const setUri = (index: number, value: string): void => {
        onChange({ redirect_uris: data.redirect_uris.map((uri, i) => (i === index ? value : uri)) });
    };

    return (
        <>
            <Box>
                <Typography sx={{ fontSize: '0.875rem', mb: 1 }}>
                    {t('services.form.redirect_uris')}
                </Typography>

                <Stack spacing={1}>
                    {data.redirect_uris.map((uri, index) => (
                        <TextField
                            key={index}
                            size="small"
                            value={uri}
                            onChange={(e) => setUri(index, e.target.value)}
                            placeholder="https://example.com/auth/callback"
                        />
                    ))}
                </Stack>

                <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>
                    {errors.redirect_uris ?? t('services.form.redirect_uris_hint')}
                </Typography>

                <Button
                    size="small"
                    color="inherit"
                    sx={{ mt: 0.5 }}
                    onClick={() => onChange({ redirect_uris: [...data.redirect_uris, ''] })}
                >
                    {t('services.form.redirect_add')}
                </Button>
            </Box>

            <TextField
                label={t('services.form.settings_url')}
                value={data.settings_url}
                onChange={(e) => onChange({ settings_url: e.target.value })}
                error={Boolean(errors.settings_url)}
                helperText={errors.settings_url ?? t('services.form.settings_url_hint')}
            />

            <TextField
                label={t('services.form.icon_url')}
                value={data.icon_url}
                onChange={(e) => onChange({ icon_url: e.target.value })}
                error={Boolean(errors.icon_url)}
                helperText={errors.icon_url ?? t('services.form.icon_url_hint')}
            />
        </>
    );
}

export default ServiceUrlFields;
