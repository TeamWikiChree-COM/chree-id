import { Link } from '@inertiajs/react';
import Typography from '@mui/material/Typography';
import AuthLayout from '../../Components/AuthLayout';

interface ClaimFailedProps {
    /** 理由ごとにサーバ側で選んだ文言 */
    message: string;
}

export default function ClaimFailed({ message }: ClaimFailedProps) {
    return (
        <AuthLayout
            title="ChreeID を作成できません"
            heading="ChreeID を作成できません"
            footer={
                <Typography variant="body2">
                    <Link href="/login">ログイン</Link>
                </Typography>
            }
        >
            <Typography variant="body2">{message}</Typography>
        </AuthLayout>
    );
}
