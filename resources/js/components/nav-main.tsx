import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { isNavHrefActive } from '@/lib/utilities';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/**
 * One section of the brand-coloured sidebar. An item stays highlighted (a
 * white pill) on the pages under it, such as a project or a record in the
 * workspace, unless it is marked `exact`.
 */
export function NavMain({
    items,
    label,
    className,
}: {
    items: NavItem[];
    label?: string;
    className?: string;
}) {
    const { currentUrl } = useCurrentUrl();

    return (
        <SidebarGroup
            className={cn(
                'px-3 py-0 group-data-[collapsible=icon]:px-2',
                className,
            )}
        >
            {label && (
                <SidebarGroupLabel className="mb-1 h-6 px-2 text-[0.68rem] font-semibold tracking-wider text-white/60 uppercase">
                    {label}
                </SidebarGroupLabel>
            )}
            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={
                                item.isActive ||
                                isNavHrefActive(
                                    toUrl(item.href),
                                    currentUrl,
                                    item.exact,
                                )
                            }
                            tooltip={{ children: item.title }}
                            className="h-9 gap-3 px-2.5 text-white/85 hover:text-white data-[active=true]:bg-white data-[active=true]:text-brand data-[active=true]:shadow-sm data-[active=true]:hover:bg-white data-[active=true]:hover:text-brand [&>svg]:text-white/70 hover:[&>svg]:text-white data-[active=true]:[&>svg]:text-brand"
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
