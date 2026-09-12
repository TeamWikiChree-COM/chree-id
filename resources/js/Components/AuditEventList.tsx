import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { formatDateTime } from '../lib/datetime';
import { t } from '../lib/i18n';
import type { TranslationKey } from '../lib/i18n';
import { methodLabel } from '../lib/credentials';
import type { AuditEventSummary } from '../types';

/**
 * 行の下に添える手がかり。
 *
 * context は行ごとに形が違う。読めるものだけを拾い、知らない形は黙って飛ばす
 * (新しい種類の記録が増えても、古い画面が壊れないようにするため)。
 *
 * @param event 記録の1行
 */
function detailOf(event: AuditEventSummary): string {
    const parts: string[] = [];
    const { method, provider, type, count, action } = event.context;

    if (typeof method === 'string') parts.push(methodLabel(method));
    if (typeof provider === 'string') parts.push(methodLabel(`oauth:${provider}`));
    if (typeof type === 'string') parts.push(methodLabel(type));
    if (typeof action === 'string') parts.push(action);
    if (typeof count === 'number' && count > 1) parts.push(String(count));

    parts.push(event.label);
    if (event.ipAddress !== null) parts.push(event.ipAddress);

    const at = formatDateTime(event.at);
    if (at !== null) parts.push(at);

    return parts.join(' ・ ');
}

interface AuditEventListProps {
    events: AuditEventSummary[];
    /** 1件も無いときの文言 */
    emptyText: string;
}

/**
 * 監査ログの一覧。
 *
 * 本人の画面と運営の画面で同じものを出す。違うのは並ぶ行の範囲だけなので、
 * 見え方まで変えると、同じ出来事を読み比べるときに突き合わせできない。
 */
export default function AuditEventList({ events, emptyText }: AuditEventListProps) {
    return (
        <Paper variant="outlined">
            {events.length === 0 && (
                <Typography sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                    {emptyText}
                </Typography>
            )}

            <Stack divider={<Box sx={{ borderBottom: '1px solid', borderColor: 'divider' }} />}>
                {events.map((event) => (
                    <Box key={event.id} sx={{ px: 2, py: 1.5 }}>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                            <Typography sx={{ fontSize: '0.9375rem' }}>
                                {t(`audit.action.${event.action}` as TranslationKey)}
                            </Typography>

                            {!event.succeeded && (
                                <Chip size="small" color="error" variant="outlined" label={t('settings.activity.failed')} />
                            )}

                            {event.byOther && (
                                <Chip size="small" variant="outlined" label={t('settings.activity.by_other')} />
                            )}
                        </Stack>

                        <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                            {detailOf(event)}
                        </Typography>
                    </Box>
                ))}
            </Stack>
        </Paper>
    );
}
