import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';

interface StatCardProps {
    label: string;
    value: number;
}

/**
 * 数を1つ見せる枠。
 */
export default function StatCard({ label, value }: StatCardProps) {
    return (
        <Paper variant="outlined" sx={{ flex: '1 1 0', p: 2 }}>
            <Typography sx={{ fontSize: '0.8125rem', color: 'text.secondary' }}>{label}</Typography>
            <Typography sx={{ fontSize: '1.5rem', fontWeight: 700 }}>{value}</Typography>
        </Paper>
    );
}
