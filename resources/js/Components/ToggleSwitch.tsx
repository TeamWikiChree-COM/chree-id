import Box from '@mui/material/Box';

interface ToggleSwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    /** 読み上げ用。周りのラベルと重複してよい */
    label: string;
    disabled?: boolean;
}

/**
 * WikiChree の switch と同じ形のトグル。
 *
 * MUI の Switch とは形が違う (向こうは track より thumb が大きく飛び出さない) ので、
 * MUI では出せない。WikiChree の sys/switch.css を写して、色だけテーマから取る。
 *
 * 14px の細いトラックに 20px の円が乗り、塗りは ::after の幅で伸びる。
 */
export default function ToggleSwitch({ checked, onChange, label, disabled = false }: ToggleSwitchProps) {
    return (
        <Box
            component="label"
            sx={{
                width: 38,
                height: 20,
                position: 'relative',
                display: 'inline-block',
                flex: 'none',
                opacity: disabled ? 0.5 : 1,
                cursor: disabled ? 'default' : 'pointer',
            }}
        >
            <Box
                component="input"
                type="checkbox"
                checked={checked}
                disabled={disabled}
                aria-label={label}
                onChange={(event) => onChange(event.currentTarget.checked)}
                sx={{
                    // 見た目は下の2つで作るので、入力そのものは隠す。
                    // display:none にすると読み上げから外れるため、透明にして重ねる
                    position: 'absolute',
                    inset: 0,
                    width: '100%',
                    height: '100%',
                    m: 0,
                    opacity: 0,
                    cursor: 'inherit',
                    zIndex: 2,
                }}
            />

            {/* トラック */}
            <Box
                aria-hidden
                sx={{
                    position: 'absolute',
                    top: 3,
                    left: 0,
                    right: 0,
                    height: 14,
                    borderRadius: 7,
                    bgcolor: 'action.disabled',
                    overflow: 'hidden',
                    '&::after': {
                        content: '""',
                        display: 'block',
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        height: '100%',
                        width: checked ? '100%' : 0,
                        borderRadius: 7,
                        bgcolor: 'primary.light',
                        opacity: 0.5,
                        transition: 'width 0.5s',
                    },
                }}
            />

            {/* つまみ */}
            <Box
                aria-hidden
                sx={{
                    position: 'absolute',
                    top: 0,
                    left: checked ? 18 : 0,
                    width: 20,
                    height: 20,
                    borderRadius: '50%',
                    bgcolor: checked ? 'primary.main' : 'grey.100',
                    boxShadow: '0 2px 2px rgba(0, 0, 0, 0.2)',
                    transition: 'left 0.5s, background-color 0.5s',
                }}
            />
        </Box>
    );
}
