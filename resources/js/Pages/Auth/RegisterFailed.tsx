import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';

interface RegisterFailedProps {
    /** 理由ごとにサーバ側で選んだ文言 */
    message: string;
}

export default function RegisterFailed({ message }: RegisterFailedProps) {
    return (
        <AuthLayout
            title="登録を完了できません"
            footer={
                <Typography variant="body2">
                    <Link href="/register">登録をやり直す</Link>
                </Typography>
            }
        >
            <Typography variant="body2">{message}</Typography>
        </AuthLayout>
    );
}
