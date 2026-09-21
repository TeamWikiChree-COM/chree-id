import Chip from '@mui/material/Chip';
import Stack from '@mui/material/Stack';
import { t } from '../../../lib/i18n';
import type { AdminAccountIssue } from './types';

const ISSUE_LABELS: Record<AdminAccountIssue, string> = {
    origin_behind: t('admin.accounts.issues.kind.origin_behind'),
    missing_user_account: t('admin.accounts.issues.kind.missing_user_account'),
    multi_service: t('admin.accounts.issues.kind.multi_service'),
};

/**
 * 種別と実体の食い違いを並べる。
 */
export default function IssueChips({ issues }: { issues: AdminAccountIssue[] }) {
    return (
        <Stack direction="row" spacing={1} sx={{ mt: 0.5, flexWrap: 'wrap' }}>
            {issues.map((issue) => (
                <Chip key={issue} size="small" color="warning" variant="outlined" label={ISSUE_LABELS[issue]} />
            ))}
        </Stack>
    );
}
