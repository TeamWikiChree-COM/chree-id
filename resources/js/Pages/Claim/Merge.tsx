import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import { router } from "@inertiajs/react";
import AuthLayout from "../../Components/AuthLayout";
import MergePanel from "./MergePanel";
import type { SignedInAccount, TransferableCredential } from "./MergePanel";

interface ClaimMergeProps {
    /** サービスから渡された平文トークン */
    token: string;
    /** 寄せ元のサービス名 */
    serviceName: string;
    /** ログイン中のアカウント */
    signedInAs: SignedInAccount | null;
    /** 持っていける認証手段 */
    transferable: TransferableCredential[];
}

/**
 * 既に持っている ChreeID へ寄せる画面。
 */
export default function ClaimMerge({ token, serviceName, signedInAs, transferable }: ClaimMergeProps) {
    return (
        <AuthLayout title="お持ちの ChreeID に追加" heading="お持ちの ChreeID に追加">
            <MergePanel
                token={token}
                serviceName={serviceName}
                signedInAs={signedInAs}
                transferable={transferable}
            />

            <Stack direction="row">
                <Button color="inherit" size="small" onClick={() => router.get(`/claim/${token}`)}>
                    戻る
                </Button>
            </Stack>
        </AuthLayout>
    );
}
