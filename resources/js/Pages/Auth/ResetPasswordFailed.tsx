import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';

export default function ResetPasswordFailed() {
    return (
        <AuthLayout
            title="このリンクは使えません"
            heading="このリンクは使えません"
            footer={
                <Typography variant="body2">
                    <Link href="/password/forgot">もう一度メールを送る</Link>
                </Typography>
            }
        >
            <Typography variant="body2">
                リンクの期限が切れているか、すでに使用されています。お手数ですが、もう一度お試しください。
            </Typography>
        </AuthLayout>
    );
}
