import MenuItem from '@mui/material/MenuItem';
import Icon from './Icon';

interface MenuLinkProps {
    /** Font Awesome の名前 */
    icon: string;
    label: string;
    onClick: () => void;
}

/**
 * アカウントメニューの1行。
 *
 * アイコンの幅を固定して、文字の頭を一列に揃える。
 */
export default function MenuLink({ icon, label, onClick }: MenuLinkProps) {
    return (
        <MenuItem onClick={onClick}>
            <Icon name={icon} sx={{ width: 20, mr: 1, fontSize: '0.875rem', color: 'text.disabled' }} />
            {label}
        </MenuItem>
    );
}
