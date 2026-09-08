import MenuItem from '@mui/material/MenuItem';

interface MenuLinkProps {
    label: string;
    onClick: () => void;
}

/**
 * アカウントメニューの1行。
 */
export default function MenuLink({ label, onClick }: MenuLinkProps) {
    return (
        <MenuItem onClick={onClick}>
            {label}
        </MenuItem>
    );
}
