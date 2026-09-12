import { useForm } from '@inertiajs/react';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import FormControlLabel from '@mui/material/FormControlLabel';
import Paper from '@mui/material/Paper';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { ChangeEvent, FormEvent } from 'react';
import Icon from '../Icon';
import type { IconSourceValue } from '../../types';

interface IconSectionProps {
    /** いまの出どころ */
    iconSource: IconSourceValue;
    /** いま表示されている絵。未設定なら null */
    iconUrl: string | null;
    /** メールアドレスを持っているか。無ければ Gravatar は選べない */
    hasEmail: boolean;
}

/**
 * アカウントのアイコン。
 *
 * 出どころを選ばせてから、必要なときだけ画像を求める。先にファイル選択を出すと、
 * Gravatar を使いたい人にも関係のない操作を見せることになる。
 */
export default function IconSection({ iconSource, iconUrl, hasEmail }: IconSectionProps) {
    const form = useForm<{ source: IconSourceValue; icon: File | null }>({ source: iconSource, icon: null });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.post('/profile/icon', { forceFormData: true, onSuccess: () => form.setData('icon', null) });
    };

    const pick = (event: ChangeEvent<HTMLInputElement>): void => {
        form.setData('icon', event.target.files?.[0] ?? null);
    };

    return (
        <Paper variant="outlined" sx={{ p: 2 }}>
            <Box component="form" onSubmit={submit} noValidate>
                <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                    <Avatar src={iconUrl ?? undefined} sx={{ width: 64, height: 64 }}>
                        <Icon name="circle-user" />
                    </Avatar>

                    <RadioGroup
                        value={form.data.source}
                        onChange={(e) => form.setData('source', e.target.value as IconSourceValue)}
                    >
                        <FormControlLabel value="none" control={<Radio />} label="設定しない" />
                        <FormControlLabel
                            value="gravatar"
                            control={<Radio />}
                            disabled={!hasEmail}
                            label={hasEmail ? 'Gravatar を使う' : 'Gravatar を使う (メールアドレスが必要)'}
                        />
                        <FormControlLabel value="upload" control={<Radio />} label="画像をアップロードする" />
                    </RadioGroup>

                    {form.data.source === 'upload' && (
                        <Button component="label" variant="outlined" color="inherit">
                            {form.data.icon?.name ?? '画像を選ぶ'}
                            <input type="file" accept="image/*" hidden onChange={pick} />
                        </Button>
                    )}

                    {form.errors.icon && (
                        <Typography sx={{ fontSize: '0.8125rem', color: 'error.main' }}>{form.errors.icon}</Typography>
                    )}
                    {form.errors.source && (
                        <Typography sx={{ fontSize: '0.8125rem', color: 'error.main' }}>
                            {form.errors.source}
                        </Typography>
                    )}

                    <Button type="submit" variant="contained" disabled={form.processing}>
                        保存する
                    </Button>
                </Stack>
            </Box>
        </Paper>
    );
}
