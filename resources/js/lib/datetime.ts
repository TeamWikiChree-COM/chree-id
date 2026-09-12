/**
 * 画面に出す日時の整形。
 *
 * サーバは "2026-09-12 00:20:32" (アプリのタイムゾーン) で寄こす。
 * `new Date(文字列)` はこの形の扱いがブラウザ任せで、Safari では NaN になることがあるので
 * 自前で分解する。
 */
import { t } from './i18n';

/** "YYYY-MM-DD HH:MM:SS" を分解する */
const PATTERN = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/;

/**
 * @param value サーバが寄こした日時文字列
 * @returns 解釈できなければ null
 */
function parse(value: string | null): Date | null {
    if (value === null) return null;

    const m = PATTERN.exec(value);
    if (m === null) return null;

    return new Date(
        Number(m[1]),
        Number(m[2]) - 1,
        Number(m[3]),
        Number(m[4]),
        Number(m[5]),
        Number(m[6] ?? '0'),
    );
}

/**
 * 「2026/09/12 00:20」の形にする。
 *
 * 登録日や期限のように、いつのことかを正確に見せたいときに使う。
 *
 * @param value サーバが寄こした日時文字列
 * @returns 解釈できなければ null
 */
export function formatDateTime(value: string | null): string | null {
    const date = parse(value);
    if (date === null) return null;

    const pad = (n: number): string => String(n).padStart(2, '0');

    return `${String(date.getFullYear())}/${pad(date.getMonth() + 1)}/${pad(date.getDate())} `
        + `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** しきい値 (秒) と、その範囲でのキー。英語で複数形を作らずに済むよう、単位は :count に添えるだけの形にしてある */
const UNITS: { limit: number; per: number; key: 'common.datetime.seconds_ago' | 'common.datetime.minutes_ago' | 'common.datetime.hours_ago' | 'common.datetime.days_ago' }[] = [
    { limit: 60, per: 1, key: 'common.datetime.seconds_ago' },
    { limit: 60 * 60, per: 60, key: 'common.datetime.minutes_ago' },
    { limit: 60 * 60 * 24, per: 60 * 60, key: 'common.datetime.hours_ago' },
    { limit: 60 * 60 * 24 * 30, per: 60 * 60 * 24, key: 'common.datetime.days_ago' },
];

/**
 * 「3分前」のように、どれくらい前かで表す。
 *
 * 最終利用のように「いつだったか」より「最近かどうか」が知りたい値に使う。
 * 1か月より前は相対で言っても掴めないので、日付に戻す。
 *
 * @param value サーバが寄こした日時文字列
 * @returns 解釈できなければ null
 */
export function formatRelative(value: string | null): string | null {
    const date = parse(value);
    if (date === null) return null;

    const seconds = (Date.now() - date.getTime()) / 1000;
    if (seconds < 0) return formatDateTime(value);
    if (seconds < 10) return t('common.datetime.just_now');

    for (const unit of UNITS) {
        if (seconds < unit.limit) return t(unit.key, { count: Math.floor(seconds / unit.per) });
    }

    return formatDateTime(value);
}
