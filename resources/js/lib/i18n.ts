import ja from '../../../lang/client/ja_jp.json';
import en from '../../../lang/client/en_us.json';

/**
 * 画面の文言。
 *
 * **正は lang/client/*.json** (ARCHITECTURE.md 10章)。Vite がビルド時に
 * バンドルへ畳み込むので、**辞書のための通信も実行時のパースも発生しない**。
 * サーバ側が PHP へ落としているのは OPcache に載せて毎リクエストの json_decode を
 * 消すためで、フロントにその事情は無い。
 *
 * **lang/server/ は読まない。** メール本文やサーバ間 API の文言まで載せると、
 * 画面で使わないものを全利用者のブラウザへ配ることになる。
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

/**
 * 基準ロケール (lang/client/ja_jp.json) が持つキー。
 *
 * 他ロケールに欠けがあれば `lang:build` が落とし、**tsc も同時に落ちる**
 * (CATALOGS が共通のキーを持たなくなるため)。二重に守られている。
 */
export type TranslationKey = keyof typeof ja;

/** `:name` に差し込む値 */
export type Replacements = Readonly<Record<string, string | number>>;

/**
 * 使うロケール。
 *
 * **app.tsx から渡してもらう形にはしない。** `import.meta.glob(eager: true)` が
 * 全ページを app.tsx の setup() より先に評価するので、モジュールスコープで `t()` を
 * 呼んでいる箇所（種別のラベル表など）が既定のロケールで固まってしまう。
 * サーバが決めた言語はその時点で既に DOM にあるため、ここで自分で読む。
 */
const current: Locale = detectLocale();

/**
 * サーバが決めた言語を DOM から読む。
 *
 * **`<html lang>` を見る。** Inertia の props にも locale は載っているが、
 * どこにどう埋まるかは Inertia の版で変わる (v3 で `<div id="app" data-page>` から
 * `<script data-page type="application/json">` の中身へ移り、それに気づかず
 * 既定のロケールに落ち続けていた)。lang 属性は HTML の仕様で、埋め方が変わらない。
 *
 * @returns 読めなければ基準ロケール
 */
function detectLocale(): Locale {
    // DOM の無いところ (テストなど) から読まれることがある
    if (typeof document === 'undefined') return 'ja';

    // "ja-JP" のような地域付きで来ても頭だけ見る
    const tag = document.documentElement.lang.split('-')[0]?.toLowerCase() ?? '';

    return tag in CATALOGS ? (tag as Locale) : 'ja';
}

/**
 * 文言を引く。
 *
 * **基準ロケールへ落とさない** (AGENTS.md 6章)。キーの過不足は lang:build が
 * 落とすので、片方にだけ在るキーはそもそも存在しない。落とし先を書くと、
 * 翻訳漏れが英語混じりの画面として黙って出てしまう。
 *
 * @param key lang/client/ja_jp.json のキー
 * @param replacements `:name` 形式の差し込み
 * @returns その言語の文言
 */
export function t(key: TranslationKey, replacements?: Replacements): string {
    const text = CATALOGS[current][key];

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

/**
 * 言語の名前は、**その言語自身の表記で出す**。
 *
 * いま読めない言語に切り替えたい人が探せるようにするため
 * (日本語の画面で "英語" と出すと、英語しか読めない人には見つけられない)。
 * 辞書には入れない。ロケールを足したらここに1行足す。
 */
const LABELS: Record<Locale, string> = { ja: '日本語', en: 'English' };

/**
 * @param locale 言語の名前
 * @returns その言語自身の表記。知らないものはそのまま返す
 */
export function localeLabel(locale: string): string {
    return locale in LABELS ? LABELS[locale as Locale] : locale;
}
