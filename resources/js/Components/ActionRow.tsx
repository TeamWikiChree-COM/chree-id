import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import ListRow from './ListRow';

interface ActionRowProps {
    label: string;
    /** 押すと何が起きるか */
    description?: string;
    /** 取り消せない操作か。赤で出す */
    destructive?: boolean;
    onClick: () => void;
}

/**
 * 設定画面の操作1行。OutlinedList の中に縦に並べる。
 *
 * ボタンを横に並べない。何が起きるかを添えられず、数が増えると押し間違えやすいため。
 */
export default function ActionRow({ label, description, destructive = false, onClick }: ActionRowProps) {
    return (
        <ListRow onClick={onClick}>
            <Box sx={{ minWidth: 0 }}>
                <Typography sx={{ fontSize: '0.9375rem', color: destructive ? 'error.main' : 'text.primary' }}>{label}</Typography>
                {description !== undefined && (
                    <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>{description}</Typography>
                )}
            </Box>
        </ListRow>
    );
}
