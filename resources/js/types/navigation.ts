import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /**
     * Highlight only on the exact URL. By default the item also stays active
     * on the pages under it (Projects on `/projects/5`).
     */
    exact?: boolean;
};

/** A titled section of the sidebar navigation. */
export type NavGroup = {
    label: string;
    items: NavItem[];
};
