import { useState } from 'react';
import type { ReactNode } from 'react';
import ConfirmDialog from '../Components/ConfirmDialog';
import type { ConfirmRequest } from '../Components/ConfirmDialog';

interface UseConfirm {
    /** 確認を挟んでから実行する。押されなければ何も起きない */
    ask: (request: ConfirmRequest) => void;
    /** 画面のどこかに1つだけ置く */
    dialog: ReactNode;
}

/**
 * 取り消せない操作の前に確認を挟む。
 *
 * ダイアログを画面ごとに組み立てずに済むよう、状態と描画をまとめて返す。
 *
 * ```tsx
 * const { ask, dialog } = useConfirm();
 * <Button onClick={() => ask({ title: '…', description: '…', onConfirm: () => router.post('…') })} />
 * {dialog}
 * ```
 */
export function useConfirm(): UseConfirm {
    const [request, setRequest] = useState<ConfirmRequest | null>(null);

    return {
        ask: setRequest,
        dialog: <ConfirmDialog request={request} onClose={() => setRequest(null)} />,
    };
}
