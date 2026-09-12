import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import Icon from '../Icon';
import { t } from '../../lib/i18n';

interface RecoveryCodesProps {
    /** 発行直後の平文。**この画面を離れると二度と手に入らない** */
    codes: string[];
}

/**
 * 発行直後の復旧コードと、その控えかた。
 *
 * **サーバに取りに行かない。** 平文を持っているのは発行の1回だけで、
 * 再取得の口を作ると「一度きり」という前提そのものが崩れる。
 * 手元にある値からファイルを組み立てて渡す。
 */
export default function RecoveryCodes({ codes }: RecoveryCodesProps) {
    const [copied, setCopied] = useState(false);

    const text = codes.join('\n') + '\n';

    const download = (): void => {
        const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
        const link = document.createElement('a');

        link.href = url;
        link.download = t('settings.recovery.filename');
        link.click();

        // 作った URL は自分で解放する。放っておくとページを離れるまで残る
        URL.revokeObjectURL(url);
    };

    const copy = (): void => {
        void navigator.clipboard.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <Alert severity="warning" sx={{ mb: 1.5 }}>
            <Typography sx={{ fontSize: '0.875rem', mb: 0.5 }}>
                {t('settings.security.recovery.once')}
            </Typography>

            <Box component="pre" sx={{ m: 0, mb: 1, fontSize: '0.8125rem' }}>
                {codes.join('\n')}
            </Box>

            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
                <Button
                    size="small"
                    variant="outlined"
                    color="inherit"
                    startIcon={<Icon name="download" />}
                    onClick={download}
                >
                    {t('settings.recovery.download')}
                </Button>
                <Button
                    size="small"
                    variant="outlined"
                    color="inherit"
                    startIcon={<Icon name={copied ? 'check' : 'copy'} />}
                    onClick={copy}
                >
                    {copied ? t('settings.recovery.copied') : t('settings.recovery.copy')}
                </Button>
            </Stack>
        </Alert>
    );
}
