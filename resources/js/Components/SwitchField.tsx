import FormControlLabel from '@mui/material/FormControlLabel';
import Switch from '@mui/material/Switch';

interface SwitchFieldProps {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}

/**
 * ラベル付きのスイッチ。フォームの真偽値の項目に使う。
 */
export default function SwitchField({ label, checked, onChange }: SwitchFieldProps) {
    return (
        <FormControlLabel
            control={<Switch checked={checked} onChange={(e) => onChange(e.target.checked)} />}
            label={label}
        />
    );
}
