import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Typography from '@mui/material/Typography';

/** 引き継ぎ候補の認証手段 */
export interface PickableCredential {
    id: string;
    type: string;
}

interface CredentialPickerProps {
    options: PickableCredential[];
    /** 選ばれている認証手段のID */
    selected: string[];
    onChange: (selected: string[]) => void;
    /** 一覧の上に出す指示 */
    instruction: string;
    /** 一覧の下に出す補足 */
    note: string;
    /** 種別ごとの表示名。無い種別は種別名のまま出す */
    labels: Record<string, string>;
    error?: string;
}

/**
 * 移行・統合・分離で、どの認証手段を持っていくかを項目ごとに選ばせる。
 */
export default function CredentialPicker({ options, selected, onChange, instruction, note, labels, error }: CredentialPickerProps) {
    const toggle = (id: string): void => {
        onChange(selected.includes(id) ? selected.filter((chosen) => chosen !== id) : [...selected, id]);
    };

    return (
        <Box sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, p: 2 }}>
            <Typography variant="body2" sx={{ mb: 1 }}>
                {instruction}
            </Typography>
            {options.map((option) => (
                <FormControlLabel
                    key={option.id}
                    control={<Checkbox checked={selected.includes(option.id)} onChange={() => toggle(option.id)} />}
                    label={labels[option.type] ?? option.type}
                    sx={{ display: 'flex' }}
                />
            ))}
            <Typography variant="body2" color="text.secondary">
                {note}
            </Typography>
            {error && (
                <Alert severity="error" sx={{ mt: 1 }}>
                    {error}
                </Alert>
            )}
        </Box>
    );
}
