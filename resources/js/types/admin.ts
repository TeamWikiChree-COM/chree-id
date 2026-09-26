/**
 * 管理画面だけが使う型。
 */

/** アプリケーションログの1件。PHP の LogFile が組み立てる */
export interface LogEntry {
    at: string;
    channel: string;
    /** ERROR / WARNING など */
    level: string;
    message: string;
    /** スタックトレース。無ければ空文字 */
    trace: string;
}
