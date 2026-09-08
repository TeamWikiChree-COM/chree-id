import Box from '@mui/material/Box';
import type { SxProps, Theme } from '@mui/material/styles';

interface IconProps {
    /** Font Awesome の名前。接頭辞なしで渡す (例: "key") */
    name: string;
    /** 既定は solid。ブランドマークは "brands" */
    family?: 'solid' | 'regular' | 'brands';
    sx?: SxProps<Theme>;
}

/**
 * Font Awesome のアイコン。
 *
 * MUI のアイコンパッケージを入れず、DokuFarm と同じ Font Awesome を CDN から使う。
 * <i> を直に書くと className の綴り間違いに気付けないので、ここを通す。
 */
export default function Icon({ name, family = 'solid', sx }: IconProps) {
    return <Box component="i" className={`fa-${family} fa-${name}`} aria-hidden sx={sx} />;
}
