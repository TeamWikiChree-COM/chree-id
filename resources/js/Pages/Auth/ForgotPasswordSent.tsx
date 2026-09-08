import { Link } from '@inertiajs/react';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';

interface ForgotPasswordSentProps {
    /** 送信先。登録されていないアドレスでもここには出す（応答を変えないため） */
    email: string;
}

export default function ForgotPasswordSent({ email }: ForgotPasswordSentProps) {
    return (
        <AuthLayout
            title="再設定メールを送りました"
            heading="再設定メールを送りました"
            footer={
                <Typography variant="body2">
                    <Link href="/login">ログイン画面に戻る</Link>
                </Typography>
            }
        >
            <Stack spacing={2}>
                <Typography variant="body2">{email} 宛にメールを送りました。</Typography>
                <Typography variant="body2" color="text.secondary">
                    本文のリンクを開くと、新しいパスワードを設定できます。
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    届かない場合は、迷惑メールに振り分けられていないかご確認ください。
                </Typography>
            </Stack>
        </AuthLayout>
    );
}
