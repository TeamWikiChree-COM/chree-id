import { usePage } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Paper from '@mui/material/Paper';
import AppLayout from '../../../Components/AppLayout';
import AuditEventList from '../../../Components/AuditEventList';
import SectionTitle from '../../../Components/SectionTitle';
import { t } from '../../../lib/i18n';
import type { AuditEventSummary, CredentialSummary } from '../../../types';
import AccountActions from './AccountActions';
import AccountCredentials from './AccountCredentials';
import AccountLinks from './AccountLinks';
import AccountRow from './AccountRow';
import IssueChips from './IssueChips';
import type { AdminAccount, AdminAccountIssue, AdminServiceLink } from './types';

interface ShowProps {
    account: AdminAccount;
    credentials: CredentialSummary[];
    /** 分離のときに持っていける認証手段のID */
    splittable: string[];
    links: AdminServiceLink[];
    issues: AdminAccountIssue[];
    events: AuditEventSummary[];
    selfId: string | null;
    graceDays: number;
}

/**
 * 管理画面のアカウント詳細。問い合わせを受けたら、まずここで全体を見る。
 */
export default function Show({ account, credentials, splittable, links, issues, events, selfId, graceDays }: ShowProps) {
    const { errors } = usePage().props;
    const isSelf = account.id === selfId;

    return (
        <AppLayout
            title={account.displayName || account.email || account.id}
            crumbs={[
                { label: t('admin.crumb'), href: '/admin' },
                { label: t('admin.accounts.crumb'), href: '/admin/accounts' },
                { label: t('admin.accounts.detail.crumb') },
            ]}
        >
            {errors.account && <Alert severity="error">{errors.account}</Alert>}

            {issues.length > 0 && (
                <Alert severity="warning">
                    {t('admin.accounts.detail.has_issues')}
                    <IssueChips issues={issues} />
                </Alert>
            )}

            <Paper variant="outlined">
                <AccountRow account={account} isSelf={isSelf} graceDays={graceDays} />
            </Paper>

            {!isSelf && (
                <>
                    <SectionTitle>{t('admin.accounts.detail.actions')}</SectionTitle>
                    <AccountActions account={account} graceDays={graceDays} />
                </>
            )}

            <SectionTitle>{t('admin.accounts.detail.credentials')}</SectionTitle>
            <AccountCredentials accountId={account.id} credentials={credentials} readOnly={isSelf} />

            <SectionTitle>{t('admin.accounts.detail.links')}</SectionTitle>
            <AccountLinks accountId={account.id} links={links} credentials={credentials} splittable={splittable} readOnly={isSelf} />

            <SectionTitle>{t('admin.accounts.detail.events')}</SectionTitle>
            <AuditEventList events={events} emptyText={t('admin.audit.empty')} />
        </AppLayout>
    );
}
