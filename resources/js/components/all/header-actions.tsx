import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { HEADER_ACTIONS_ID } from '@/components/app-sidebar-header';

/**
 * Renders a page's controls (status, export…) at the right of the app header,
 * next to the breadcrumbs. A portal rather than a layout prop, so live
 * content such as progress updates without re-rendering the layout.
 */
export default function HeaderActions({ children }: { children: ReactNode }) {
    const [target, setTarget] = useState<HTMLElement | null>(null);

    useEffect(() => setTarget(document.getElementById(HEADER_ACTIONS_ID)), []);

    return target ? createPortal(children, target) : null;
}
