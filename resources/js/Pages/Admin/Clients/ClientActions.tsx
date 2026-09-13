import { router } from "@inertiajs/react";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Icon from "../../../Components/Icon";
import SectionTitle from "../../../Components/SectionTitle";
import { useConfirm } from "../../../lib/confirm";
import { t } from "../../../lib/i18n";
import type { OAuthClient } from "../../../types";

interface ClientActionsProps {
    client: OAuthClient;
}

/**
 * 登録済みの接続サービスに対する、取り消せない操作。
 */
export default function ClientActions({ client }: ClientActionsProps) {
    const { ask, dialog } = useConfirm();

    const remove = (): void => {
        ask({
            title: t('admin.clients.form.remove.title', { name: client.name }),
            description: t('admin.clients.form.remove.description'),
            confirmText: t('admin.clients.form.remove.confirm'),
            expected: client.name,
            onConfirm: () => router.post(`/admin/clients/${client.id}/delete`),
        });
    };

    const rotate = (): void => {
        ask({
            title: t('admin.clients.form.rotate.title'),
            description: t('admin.clients.form.rotate.description'),
            confirmText: t('admin.clients.form.rotate.confirm'),
            onConfirm: () => router.post(`/admin/clients/${client.id}/secret`),
        });
    };

    return (
        <>
            <SectionTitle>{t('admin.clients.form.actions.heading')}</SectionTitle>
            <Paper variant="outlined" sx={{ p: 2 }}>
                <Stack spacing={1.5} sx={{ alignItems: "flex-start" }}>
                    {client.isConfidential && (
                        <Button variant="outlined" color="inherit" startIcon={<Icon name="rotate" />} onClick={rotate}>
                            {t('admin.clients.form.actions.rotate')}
                        </Button>
                    )}
                    <Button variant="outlined" color="error" startIcon={<Icon name="trash" />} onClick={remove}>
                        {t('admin.clients.form.actions.remove')}
                    </Button>
                </Stack>
            </Paper>
            {dialog}
        </>
    );
}
