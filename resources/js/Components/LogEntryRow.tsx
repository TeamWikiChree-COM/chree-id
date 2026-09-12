import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { t } from '../lib/i18n';
import type { LogEntry } from '../types';

/** 目立たせる度合い。知らない level は既定の色で出す */
const COLORS: Record<string, 'error' | 'warning' | 'info'> = {
    EMERGENCY: 'error',
    ALERT: 'error',
    CRITICAL: 'error',
    ERROR: 'error',
    WARNING: 'warning',
    NOTICE: 'info',
    INFO: 'info',
};

interface LogEntryRowProps {
    entry: LogEntry;
}

/**
 * ログ1件。
 *
 * **スタックトレースは畳んでおく。** 1件が数十行あるので、開いたままだと
 * 前後に何が起きたかを追えない。
 */
export default function LogEntryRow({ entry }: LogEntryRowProps) {
    const [open, setOpen] = useState(false);

    return (
        <Box sx={{ px: 2, py: 1.5 }}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                <Chip size="small" variant="outlined" color={COLORS[entry.level]} label={entry.level} />
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{entry.at}</Typography>
                <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{entry.channel}</Typography>
            </Stack>

            <Typography
                sx={{
                    mt: 0.5,
                    fontSize: '0.875rem',
                    fontFamily: 'monospace',
                    whiteSpace: 'pre-wrap',
                    wordBreak: 'break-word',
                }}
            >
                {entry.message}
            </Typography>

            {entry.trace !== '' && (
                <>
                    <Link
                        component="button"
                        type="button"
                        onClick={() => setOpen(!open)}
                        sx={{ mt: 0.5, fontSize: '0.8125rem' }}
                    >
                        {open ? t('admin.logs.trace_hide') : t('admin.logs.trace_show')}
                    </Link>

                    {open && (
                        <Box
                            component="pre"
                            sx={{
                                mt: 1,
                                p: 1.5,
                                maxHeight: 360,
                                overflow: 'auto',
                                fontSize: '0.75rem',
                                bgcolor: 'action.hover',
                                borderRadius: 1,
                            }}
                        >
                            {entry.trace}
                        </Box>
                    )}
                </>
            )}
        </Box>
    );
}
