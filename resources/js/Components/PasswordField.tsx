import IconButton from '@mui/material/IconButton';
import TextField from '@mui/material/TextField';
import type { TextFieldProps } from '@mui/material/TextField';
import { useState } from 'react';
import Icon from './Icon';
import { t } from '../lib/i18n';

/**
 * 表示/非表示を切り替えられるパスワード入力欄。
 *
 * `type` 以外は TextField にそのまま渡す。
 */
export default function PasswordField(props: Omit<TextFieldProps, 'type'>) {
    const [visible, setVisible] = useState(false);

    return (
        <TextField
            {...props}
            type={visible ? 'text' : 'password'}
            slotProps={{
                ...props.slotProps,
                input: {
                    ...props.slotProps?.input,
                    endAdornment: (
                        <IconButton
                            aria-label={visible ? t('common.password.hide') : t('common.password.show')}
                            onClick={() => setVisible((v) => !v)}
                            edge="end"
                            size="small"
                            tabIndex={-1}
                        >
                            <Icon name={visible ? 'eye-slash' : 'eye'} sx={{ fontSize: '0.9375rem' }} />
                        </IconButton>
                    ),
                },
            }}
        />
    );
}
