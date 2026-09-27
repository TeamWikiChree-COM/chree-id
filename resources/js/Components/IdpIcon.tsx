import Box from '@mui/material/Box';
import type { SxProps, Theme } from '@mui/material/styles';
import Icon from './Icon';
import { idpColor, idpIcon, idpIconFamily, idpSvg } from '../lib/idps';

/**
 * @param svg SVG の data URI (base64)
 * @returns 幅 / 高さ。viewBox が読めなければ 1 (正方形)
 */
function aspectOf(svg: string): number {
    const viewBox = atob(svg.split(',')[1] ?? '').match(/viewBox="([^"]+)"/)?.[1];
    const [, , width = 0, height = 0] = (viewBox ?? '').trim().split(/[\s,]+/).map(Number);

    return width > 0 && height > 0 ? width / height : 1;
}

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
                {
                    display: 'inline-block',
                    // 高さは文字にそろえ、幅は SVG の縦横比から決める。正方形の枠だと横長のロゴが小さくなる
                    width: `${aspectOf(svg)}em`,
                    height: '1em',
                    verticalAlign: '-0.125em',
                    backgroundColor: idpColor(provider) ?? 'currentColor',
                    mask,
                    WebkitMask: mask,
                },
                ...(Array.isArray(sx) ? sx : [sx]),
            ]}
        />
    );
}
