import Button from '@mui/material/Button';
import type { ReactNode } from 'react';

interface RowActionProps {
    children: ReactNode;
    onClick: () => void;
    /**
     * 取り消せない操作か。
     *
     * 削除・解除・失効のように元に戻せないものは赤で出す。
     * 色は「押す前に一拍置いてもらう」ためのものなので、
     * 取り消せる操作に付けると意味が薄れる。
     */
    destructive?: boolean;
}

/**
 * 一覧の行の右端に置く操作。
 */
export default function RowAction({ children, onClick, destructive = false }: RowActionProps) {
    return (
        <Button size="small" color={destructive ? 'error' : 'inherit'} onClick={onClick}>
            {children}
        </Button>
    );
}
