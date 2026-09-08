import { Link } from '@inertiajs/react';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';

interface RegisterSentProps {
    /** 送信先。既に登録済みのアドレスでもここには出す（応答を変えないため） */
    email: string;
}

export default function RegisterSent({ email }: RegisterSentProps) {
    return (
        <AuthLayout
            title="確認メールを送りました"
            footer={
                <Typography variant="body2">
                    <Link href="/login">ログイン画面に戻る</Link>
                </Typography>
            }
        >
            <Stack spacing={2}>
                <Typography variant="body2">{email} 宛にメールを送りました。</Typography>
                <Typography variant="body2" color="text.secondary">
                    メール内のリンクを開くとアカウントが作られ、そのままセットアップに進みます。
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    届かない場合は、迷惑メールに振り分けられていないかご確認ください。
                </Typography>
            </Stack>
        </AuthLayout>
    );
}
