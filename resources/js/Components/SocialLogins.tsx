import { usePage } from '@inertiajs/react';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Tooltip from '@mui/material/Tooltip';
import Icon from './Icon';
import { idpIcon, idpIconFamily, idpLabel } from '../lib/idps';

interface SocialLoginsProps {
    /** マジックリンクの申し込み先。ログイン画面以外では省く */
    magicLinkHref?: string;
}

/**
 * パスワード以外のログイン手段。
 *
 * 外部アカウントはアイコンだけにする。Google や GitHub はマークだけで通じるため。
 * パスキーとマジックリンクは動作の名前で、指紋や封筒の絵からは意味を当てられないので
 * ラベルを付ける。ツールチップは触れないと出ないので、初見の人には届かない。
 *
 * 並べるのは設定が揃っている IdP だけ。押しても何も起きないボタンは出さない。
 */
export default function SocialLogins({ magicLinkHref }: SocialLoginsProps) {
    const { externalIdps } = usePage().props;

    return (
        <>
            {externalIdps.length > 0 && (
                <Stack direction="row" spacing={1.75} sx={{ justifyContent: 'center' }}>
                    {externalIdps.map((name) => {
                        const label = `${idpLabel(name)} で続行`;

                        return (
                            <Tooltip key={name} title={label}>
                                <IconButton
                                    component="a"
                                    href={`/auth/${name}/redirect`}
                                    aria-label={label}
                                    sx={{ width: 48, height: 48, border: '1px solid', borderColor: 'divider' }}
                                >
                                    <Icon name={idpIcon(name)} family={idpIconFamily(name)} sx={{ fontSize: '1.25rem' }} />
                                </IconButton>
                            </Tooltip>
                        );
                    })}
                </Stack>
            )}

            {magicLinkHref !== undefined && (
                <Button
                    component="a"
                    href={magicLinkHref}
                    variant="outlined"
                    color="inherit"
                    startIcon={<Icon name="envelope" />}
                >
                    マジックリンクを送る
                </Button>
            )}
        </>
    );
}
