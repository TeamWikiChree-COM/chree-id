import { createTranslator } from '@/lib/i18n';
import en from '../../../lang/en_us.json';
import ja from '../../../lang/ja_jp.json';

/** wiki-hub の文言。本体の辞書には混ぜない */
export const t = createTranslator({ ja, en });
