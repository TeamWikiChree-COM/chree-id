import { useForm } from '@inertiajs/react';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { t } from '../../lib/i18n';
import type { ConnectedService, EmailOption } from '../../types';

interface ServiceEmailFormProps {
    service: ConnectedService;
    options: EmailOption[];
    /** 割り当てが無いときに渡っているアドレス。選択肢に添えて、何が渡るかを見せる */
    primaryEmail: string | null;
}

/** 「主アドレスに合わせる」を表す選択肢の値 */
const PRIMARY = '';

/**
 * サービスへ渡すメールアドレスを選ぶ。
 *
 * 「+」に対応したドメインでは、後ろに付ける文字を足してサービスごとに見分けられるようにする。
 */
export default function ServiceEmailForm({ service, options, primaryEmail }: ServiceEmailFormProps) {
    const initial = splitAssigned(service.email, options);
    const form = useForm({ email: '' });
    const [base, setBase] = useState(initial.base);
    const [tag, setTag] = useState(initial.tag);

    const plus = options.find((option) => option.email === base)?.plus === true;
    const email = base === PRIMARY ? '' : withTag(base, plus ? tag : '');

    const submit = (): void => {
        form.transform(() => ({ email }));
        form.post(`/services/${service.id}/email`);
    };

    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                <TextField
                    select
                    label={t('dashboard.service_email.address_label')}
                    value={base}
                    onChange={(e) => setBase(e.target.value)}
                    error={Boolean(form.errors.email)}
                    helperText={form.errors.email ?? t('dashboard.service_email.hint')}
                >
                    <MenuItem value={PRIMARY}>
                        {primaryEmail === null
                            ? t('dashboard.service_email.primary')
                            : t('dashboard.service_email.primary_with', { email: primaryEmail })}
                    </MenuItem>
                    {options.map((option) => (
                        <MenuItem key={option.email} value={option.email}>
                            {option.email}
                        </MenuItem>
                    ))}
                </TextField>
                {plus && (
                    <TextField
                        label={t('dashboard.service_email.tag_label')}
                        value={tag}
                        onChange={(e) => setTag(e.target.value.replace(/[@\s]/g, ''))}
                        helperText={t('dashboard.service_email.tag_hint', { example: withTag(base, tag || 'service') })}
                    />
                )}
                {email !== '' && <Typography sx={{ fontSize: '0.875rem', overflowWrap: 'anywhere' }}>{email}</Typography>}
                <Button variant="contained" onClick={submit} disabled={form.processing}>
                    {t('settings.common.save')}
                </Button>
            </Stack>
        </Paper>
    );
}

/**
 * @param email 元のアドレス
 * @param tag 「+」の後ろに付ける文字。空なら付けない
 * @returns 組み立てたアドレス
 */
function withTag(email: string, tag: string): string {
    if (tag === '') return email;

    const at = email.lastIndexOf('@');

    return `${email.slice(0, at)}+${tag}${email.slice(at)}`;
}

/**
 * 今の割り当てを、選択肢と「+」の後ろに分ける。
 *
 * @param assigned 今の割り当て。null なら主アドレス
 * @param options 選べるアドレス
 * @returns 選択肢の値と、付けている文字
 */
function splitAssigned(assigned: string | null, options: EmailOption[]): { base: string; tag: string } {
    if (assigned === null) return { base: PRIMARY, tag: '' };

    const exact = options.find((option) => option.email.toLowerCase() === assigned.toLowerCase());
    if (exact !== undefined) return { base: exact.email, tag: '' };

    const match = /^([^+@]+)\+([^@]*)(@.+)$/.exec(assigned);
    const owner = match === null ? undefined : options.find((option) => option.plus && option.email.toLowerCase() === `${match[1]}${match[3]}`.toLowerCase());

    return owner === undefined || match === null ? { base: PRIMARY, tag: '' } : { base: owner.email, tag: match[2] ?? '' };
}
