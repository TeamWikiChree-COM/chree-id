import Box from '@mui/material/Box';
import type { SxProps, Theme } from '@mui/material/styles';
import Icon from './Icon';
import { idpColor, idpIcon, idpIconFamily, idpSvg } from '../lib/idps';

interface IdpIconProps {
    /** 'google' などの識別子 */
    provider: string;
    sx?: SxProps<Theme>;
}

/**
 * 外部 IdP のアイコン。
 *
 * SVG があれば形だけを使い、IdP の色か周りの文字色で塗る (mask)。HTML として埋め込まないので、SVG の中身で画面を書き換えられない。
 */
export default function IdpIcon({ provider, sx }: IdpIconProps) {
    const svg = idpSvg(provider);
    if (svg === null) return <Icon name={idpIcon(provider)} family={idpIconFamily(provider)} sx={sx} />;

    const mask = `url("${svg}") center / contain no-repeat`;

    return (
        <Box
            component="span"
            aria-hidden
            sx={[
                { display: 'inline-block', width: '1.6em', height: '1em', verticalAlign: '-0.125em', backgroundColor: idpColor(provider) ?? 'currentColor', mask, WebkitMask: mask },
                ...(Array.isArray(sx) ? sx : [sx]),
            ]}
        />
    );
}
