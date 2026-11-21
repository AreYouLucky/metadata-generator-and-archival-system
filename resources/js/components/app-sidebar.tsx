import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Archive,
    Building2,
    ClipboardCheck,
    FolderKanban,
    LayoutGrid,
    ScrollText,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as dispositions } from '@/routes/admin/dispositions';
import { index as logs } from '@/routes/admin/logs';
import { show as systemStatus } from '@/routes/admin/system';
import { index as users } from '@/routes/admin/users';
import { index as agencies } from '@/routes/agencies';
import { index as projects } from '@/routes/projects';
import { index as reviews } from '@/routes/reviews';
import type { NavItem } from '@/types';

const viewerNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

/**
 * Encoders and admins. Videos are uploaded from inside a project or one of
 * its series, so there is no separate "New record" entry.
 */
const archiveNavItems: NavItem[] = [
    {
        title: 'Projects',
        href: projects(),
        icon: FolderKanban,
    },
    {
        title: 'Agencies',
        href: agencies(),
        icon: Building2,
    },
    {
        title: 'Pending reviews',
        href: reviews(),
        icon: ClipboardCheck,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Users',
        href: users(),
        icon: Users,
    },
    {
        title: 'Logs',
        href: logs(),
        icon: ScrollText,
    },
    {
        title: 'Disposition review',
        href: dispositions(),
        icon: Archive,
    },
    {
        title: 'System status',
        href: systemStatus(),
        icon: Activity,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const role = auth.user?.role;
    const isStaff = role === 'admin' || role === 'encoder';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {isStaff ? (
                    <>
                        <NavMain label="Archive" items={archiveNavItems} />
                        {role === 'admin' && (
                            <NavMain
                                label="Administration"
                                items={adminNavItems}
                            />
                        )}
                    </>
                ) : (
                    <NavMain items={viewerNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
