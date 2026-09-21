import { router } from '@inertiajs/react';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Pagination from '@mui/material/Pagination';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { t } from '../../../lib/i18n';
import type { AdminAccountFilters, AdminClientOption, AdminPagination } from './types';

interface AccountFiltersProps {
    filters: AdminAccountFilters;
    clients: AdminClientOption[];
}

/**
 * 空の条件は URL に載せない。共有したリンクが読みやすいように。
 *
 * @param filters 条件
 * @param page ページ
 * @returns クエリ
 */
function toQuery(filters: AdminAccountFilters, page = 1): Record<string, string | number> {
    const query: Record<string, string | number> = {};
    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') query[key] = value;
    }
    if (page > 1) query.page = page;

    return query;
}

/**
 * アカウント一覧の絞り込み。条件を変えたら1ページ目へ戻す。
 */
export function AccountFilters({ filters, clients }: AccountFiltersProps) {
    const [draft, setDraft] = useState<AdminAccountFilters>(filters);

    const apply = (next: AdminAccountFilters): void => {
        setDraft(next);
        router.get('/admin/accounts', toQuery(next), { preserveState: true });
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        apply(draft);
    };

    return (
        <Stack component="form" onSubmit={submit} direction="row" spacing={1.5} sx={{ flexWrap: 'wrap', rowGap: 1.5 }}>
            <TextField
                size="small"
                label={t('admin.accounts.filter.query')}
                placeholder={t('admin.accounts.filter.query_placeholder')}
                value={draft.q}
                onChange={(e) => setDraft({ ...draft, q: e.target.value })}
                sx={{ minWidth: 260 }}
            />
            <TextField select size="small" label={t('admin.accounts.filter.kind')} value={draft.kind}
                onChange={(e) => apply({ ...draft, kind: e.target.value as AdminAccountFilters['kind'] })} sx={{ minWidth: 160 }}>
                <MenuItem value="">{t('admin.accounts.filter.all')}</MenuItem>
                <MenuItem value="user">{t('admin.accounts.row.origin.user')}</MenuItem>
                <MenuItem value="service">{t('admin.accounts.row.origin.service')}</MenuItem>
            </TextField>
            <TextField select size="small" label={t('admin.accounts.filter.status')} value={draft.status}
                onChange={(e) => apply({ ...draft, status: e.target.value as AdminAccountFilters['status'] })} sx={{ minWidth: 140 }}>
                <MenuItem value="">{t('admin.accounts.filter.all')}</MenuItem>
                <MenuItem value="active">{t('admin.accounts.filter.status_active')}</MenuItem>
                <MenuItem value="suspended">{t('admin.accounts.row.suspended')}</MenuItem>
                <MenuItem value="deleted">{t('admin.accounts.row.withdrawn')}</MenuItem>
            </TextField>
            <TextField select size="small" label={t('admin.accounts.filter.client')} value={draft.client}
                onChange={(e) => apply({ ...draft, client: e.target.value })} sx={{ minWidth: 180 }}>
                <MenuItem value="">{t('admin.accounts.filter.all')}</MenuItem>
                {clients.map((client) => (
                    <MenuItem key={client.id} value={client.id}>{client.name}</MenuItem>
                ))}
            </TextField>
            <Button type="submit" variant="contained" size="small">
                {t('admin.accounts.filter.submit')}
            </Button>
        </Stack>
    );
}

interface AccountPagerProps {
    filters: AdminAccountFilters;
    pagination: AdminPagination;
}

/**
 * ページ送り。1ページしか無ければ出さない。
 */
export function AccountPager({ filters, pagination }: AccountPagerProps) {
    if (pagination.lastPage <= 1) return null;

    return (
        <Pagination
            page={pagination.page}
            count={pagination.lastPage}
            shape="rounded"
            onChange={(_, page) => router.get('/admin/accounts', toQuery(filters, page))}
            sx={{ display: 'flex', justifyContent: 'center', mt: 2 }}
        />
    );
}
