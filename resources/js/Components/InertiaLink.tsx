import { Link } from '@inertiajs/react';
import { forwardRef } from 'react';
import type { AnchorHTMLAttributes } from 'react';
import type { InertiaLinkProps } from '@inertiajs/react';

interface Props extends AnchorHTMLAttributes<HTMLAnchorElement> {
    href: string;
}

/**
 * MUI の component に渡せる Inertia のリンク。
 *
 * Inertia の Link は ref を unknown で公開しているため、そのままでは
 * MUI の OverridableComponent の推論を通らない。アンカーとして型を付け直す。
 *
 * 内側では Inertia 側の型に戻す。イベントハンドラの要素型が
 * HTMLAnchorElement と Element でずれているだけで、実体は同じ <a>。
 */
const InertiaLink = forwardRef<HTMLAnchorElement, Props>(function InertiaLink(props, ref) {
    return <Link ref={ref} {...(props as InertiaLinkProps)} />;
});

export default InertiaLink;
