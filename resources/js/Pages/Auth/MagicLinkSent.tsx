import { Link } from '@inertiajs/react';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';

interface MagicLinkSentProps {
    /** 送信先。メールログインが使えないアドレスでもここには出す（応答を変えないため） */
    email: string;
}

export default function MagicLinkSent({ email }: MagicLinkSentProps) {
    return (
        <AuthLayout
            title="ログインリンクを送りました"
            footer={
                <Typography variant="body2">
                    <Link href="/login">パスワードでログインする</Link>
                </Typography>
            }
        >
            <Stack spacing={2}>
                <Typography variant="body2">{email} 宛にメールを送りました。</Typography>
                <Typography variant="body2" color="text.secondary">
                    本文のリンクを開くとログインします。リンクは一度だけ使えます。
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    届かない場合は、メールでのログインが有効になっていないか、
                    迷惑メールに振り分けられている可能性があります。
                </Typography>
            </Stack>
        </AuthLayout>
    );
}
