import ja from '../../../lang/ja_jp.json';
import en from '../../../lang/en_us.json';

/**
 * 画面の文言。
 *
 * **正は lang/*.json で、サーバと同じものを読む** (ARCHITECTURE.md 10章)。
 * サーバ側が PHP へ落としているのは OPcache に載せて毎リクエストの json_decode を
 * 消すためで、フロントにその事情は無い。Vite がビルド時にバンドルへ畳み込むので、
 * **辞書のための通信も実行時のパースも発生しない**。
 *
 * 辞書を Inertia の shared props に載せないのは、ページ遷移のたびに
 * 全文がレスポンスへ乗るため。配るのはロケール名だけにしてある。
 */

/**
 * 明示的に import しているのは、キーを型にするため。
 * 基準ロケールの持つキーだけが `TranslationKey` になるので、打ち間違いが tsc で落ちる。
 * ロケールを増やすときはここに1行足す (JSON を置くだけでは載らない)。
 */
const CATALOGS = { ja, en } as const;

type Locale = keyof typeof CATALOGS;

/** 基準ロケール (lang/ja_jp.json) が持つキー。LangValidator が他ロケールの欠けを落とす */
export type TranslationKey = keyof typeof ja;

/** `:name` に差し込む値 */
export type Replacements = Readonly<Record<string, string | number>>;

let current: Locale = 'ja';

/**
 * 使うロケールを決める。app.tsx が起動時に一度だけ呼ぶ。
 *
 * @param locale サーバが渡したロケール名。知らないものは基準ロケールに落とす
 */
export function initTranslations(locale: string): void {
    current = locale in CATALOGS ? (locale as Locale) : 'ja';
}

/**
 * 文言を引く。
 *
 * @param key lang/ja_jp.json のキー
 * @param replacements `:name` 形式の差し込み
 * @returns 引けた文言。無ければキーをそのまま返す (画面を壊さず、直す場所が分かる)
 */
export function t(key: TranslationKey, replacements?: Replacements): string {
    const text: string | undefined = CATALOGS[current][key] ?? ja[key];

    if (text === undefined) return key;
    if (replacements === undefined) return text;

    return interpolate(text, replacements);
}

/**
 * Laravel と同じ `:name` 形式で差し込む。書式を揃えてあるので、
 * lang:build のプレースホルダ検証がフロントの文言にも効く。
 *
 * @param text 元の文言
 * @param replacements 差し込む値
 * @returns 差し込んだ文言
 */
function interpolate(text: string, replacements: Replacements): string {
    // `:name` と `:nameSuffix` が並んだときに短いほうから食われないよう、長いキーを先に
    const names = Object.keys(replacements).sort((a, b) => b.length - a.length);

    return names.reduce(
        (carried, name) => carried.replaceAll(`:${name}`, String(replacements[name])),
        text,
    );
}
