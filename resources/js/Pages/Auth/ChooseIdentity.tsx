import { router } from "@inertiajs/react";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import { useState } from "react";
import AuthLayout from "../../Components/AuthLayout";

/** 同じ外部アカウントに紐付いている認証主体 */
interface ChoosableIdentity {
    id: string;
    email: string | null;
    displayName: string | null;
}

interface ChooseIdentityProps {
    accounts: ChoosableIdentity[];
}

/**
 * どのアカウントで続けるかを選ぶ画面。
 *
 * サービスをまとめから外すと、同じ Google アカウントが複数の認証主体に紐付く。
 * 黙ってどれかを選ぶと別のアカウントとして入れてしまうので、必ず本人に選ばせる。
 */
export default function ChooseIdentity({ accounts }: ChooseIdentityProps) {
    const [sending, setSending] = useState(false);

    const choose = (id: string): void => {
        setSending(true);
        router.post("/login/choose", { account_id: id });
    };

    return (
        <AuthLayout title="アカウントを選択" heading="アカウントを選択">
            <Typography variant="body2" color="text.secondary">
                この連携には複数のアカウントがあります。どれで続けますか？
            </Typography>

            <Stack spacing={1}>
                {accounts.map((account) => (
                    <Box
                        key={account.id}
                        sx={{ border: "1px solid", borderColor: "divider", borderRadius: 2, p: 2 }}
                    >
                        <Stack
                            direction="row"
                            spacing={2}
                            sx={{ alignItems: "center", justifyContent: "space-between" }}
                        >
                            <Box sx={{ minWidth: 0 }}>
                                <Typography>{account.displayName ?? account.email ?? account.id}</Typography>
                                {account.displayName !== null && account.email !== null && (
                                    <Typography variant="body2" color="text.secondary">
                                        {account.email}
                                    </Typography>
                                )}
                            </Box>
                            <Button variant="contained" disabled={sending} onClick={() => choose(account.id)}>
                                これで続ける
                            </Button>
                        </Stack>
                    </Box>
                ))}
            </Stack>
        </AuthLayout>
    );
}
