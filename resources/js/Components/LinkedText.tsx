import Link from '@mui/material/Link';
import { Fragment } from 'react';
import InertiaLink from './InertiaLink';

/** `[文言](/path)` の形。翻訳文の中にリンクを書くための記法 */
const LINK = /\[([^\]]+)\]\(([^)\s]+)\)/g;

interface LinkedTextProps {
    /** t() で引いた文言。`[文言](/path)` の部分がリンクになる */
    text: string;
}

/**
 * 文中の「〜設定で行えます」などを、その画面へのリンクにする。
 *
 * 文言は翻訳ファイルに置いたまま、リンクの位置も言語ごとに決められるように記法で埋め込む。
 * 外へ出るリンクは想定しない。ChreeID 内の画面だけを指すこと。
 */
export default function LinkedText({ text }: LinkedTextProps) {
    const parts: Array<string | { label: string; href: string }> = [];
    let last = 0;

    for (const match of text.matchAll(LINK)) {
        parts.push(text.slice(last, match.index));
        parts.push({ label: match[1] ?? '', href: match[2] ?? '/' });
        last = match.index + match[0].length;
    }
    parts.push(text.slice(last));

    return (
        <>
            {parts.map((part, index) => (
                <Fragment key={index}>
                    {typeof part === 'string'
                        ? part
                        : <Link component={InertiaLink} href={part.href}>{part.label}</Link>}
                </Fragment>
            ))}
        </>
    );
}
