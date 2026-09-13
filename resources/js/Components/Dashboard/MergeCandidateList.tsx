import { router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import ListRow from '../ListRow';
import OutlinedList from '../OutlinedList';
import RowAction from '../RowAction';
import SectionTitle from '../SectionTitle';
import { t } from '../../lib/i18n';

/** 同じアドレスの別アカウント。挙げるだけで、勝手には統合しない */
export interface MergeCandidate {
    id: string;
    displayName: string | null;
    email: string | null;
    hasUserAccount: boolean;
}

interface MergeCandidateListProps {
    candidates: MergeCandidate[];
}

/**
 * 統合できそうなアカウントの一覧。候補が無ければ何も出さない。
 */
export default function MergeCandidateList({ candidates }: MergeCandidateListProps) {
    if (candidates.length === 0) return null;

    return (
        <>
            <SectionTitle note={t('common.count', { count: candidates.length })}>{t('dashboard.merge.heading')}</SectionTitle>
            <OutlinedList>
                {candidates.map((candidate) => (
                    <ListRow
                        key={candidate.id}
                        // 提示と実行は別処理。ここは相手側を証明する画面へ送るだけ
                        actions={
                            <RowAction onClick={() => router.get(`/settings/merge/${candidate.id}`)}>
                                {t('dashboard.merge.action')}
                            </RowAction>
                        }
                    >
                        <Box>
                            <Typography sx={{ fontSize: '0.9375rem' }}>
                                {candidate.displayName ?? candidate.email ?? candidate.id}
                            </Typography>
                            <Typography sx={{ fontSize: '0.8125rem', color: 'text.disabled' }}>
                                {candidate.hasUserAccount ? t('dashboard.merge.has_account') : t('dashboard.merge.service_created')}
                            </Typography>
                        </Box>
                    </ListRow>
                ))}
            </OutlinedList>
            <Typography sx={{ mt: 1, fontSize: '0.8125rem', color: 'text.disabled' }}>{t('dashboard.merge.note')}</Typography>
        </>
    );
}
