import { useState } from 'react';
import type { ReactNode } from 'react';
import ActionDialog from '../Components/ActionDialog';
import type { ActionRequest } from '../Components/ActionDialog';

interface UseActions {
    /** 行に対してできる操作を並べたダイアログを開く */
    open: (request: ActionRequest) => void;
    /** 画面のどこかに1つだけ置く */
    dialog: ReactNode;
}

/**
 * 一覧の行を押したときの操作選択。useConfirm と同じ形で使う。
 *
 * ```tsx
 * const { open, dialog } = useActions();
 * <ListRow onClick={() => open({ title: '…', actions: [{ label: '…', onClick: () => … }] })} />
 * {dialog}
 * ```
 */
export function useActions(): UseActions {
    const [request, setRequest] = useState<ActionRequest | null>(null);

    return {
        open: setRequest,
        dialog: <ActionDialog request={request} onClose={() => setRequest(null)} />,
    };
}
