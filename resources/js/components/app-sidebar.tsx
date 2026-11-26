import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Archive,
    BarChart3,
    Building2,
    ClipboardCheck,
    Clapperboard,
    FileVideo,
    FolderKanban,
    Gauge,
    Globe,
    LayoutDashboard,
    ScrollText,
    Tags,
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
    SidebarRail,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { overview } from '@/routes/admin';
import { index as approvals } from '@/routes/admin/approvals';
import { index as dispositions } from '@/routes/admin/dispositions';
import { index as genres } from '@/routes/admin/genres';
import { index as logs } from '@/routes/admin/logs';
import { show as systemStatus } from '@/routes/admin/system';
import { index as usage } from '@/routes/admin/usage';
import { index as users } from '@/routes/admin/users';
import { index as videoTypes } from '@/routes/admin/video-types';
import { index as agencies } from '@/routes/agencies';
import { home as catalogue } from '@/routes/catalogue';
import { index as projects } from '@/routes/projects';
import { index as records } from '@/routes/records';
import { index as reviews } from '@/routes/reviews';
import type { NavGroup, NavItem } from '@/types';

const catalogueItem: NavItem = {
    title: 'Public catalogue',
    href: catalogue(),
    icon: Globe,
};

/** Encoders and admins land here after login. */
const homeGroup: NavGroup = {
    label: 'Home',
    items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutDashboard }],
};

/**
 * Encoders and admins. Videos are uploaded from inside a project or one of
 * its series, so there is no separate "New record" entry.
 */
const archiveItems: NavItem[] = [
    { title: 'Records', href: records(), icon: FileVideo },
    { title: 'Projects', href: projects(), icon: FolderKanban },
    { title: 'Agencies', href: agencies(), icon: Building2 },
];

/** Encoders follow their submissions here; admins review in Approvals. */
const encoderGroups: NavGroup[] = [
    homeGroup,
    {
        label: 'Archive',
        items: [
            ...archiveItems,
            { title: 'Pending reviews', href: reviews(), icon: ClipboardCheck },
        ],
    },
];

const adminGroups: NavGroup[] = [
    homeGroup,
    { label: 'Archive', items: archiveItems },
    {
        label: 'Review',
        items: [
            { title: 'Approvals', href: approvals(), icon: ClipboardCheck },
            {
                title: 'Disposition review',
                href: dispositions(),
                icon: Archive,
            },
        ],
    },
    {
        label: 'Monitoring',
        items: [
            // Every admin page sits under /admin, so only the exact URL counts.
            { title: 'Overview', href: overview(), icon: Gauge, exact: true },
            { title: 'Usage', href: usage(), icon: BarChart3 },
            { title: 'Logs', href: logs(), icon: ScrollText },
            { title: 'System status', href: systemStatus(), icon: Activity },
        ],
    },
    {
        label: 'Setup',
        items: [
            { title: 'Users', href: users(), icon: Users },
            { title: 'Genres', href: genres(), icon: Tags },
            { title: 'Video types', href: videoTypes(), icon: Clapperboard },
        ],
    },
];

/**
 * The staff workspace navigation, in the brand colour (tokens in app.css),
 * with a section per area. Its header is as tall as the page header so the
 * two bottom borders run in one line. Public viewers mostly use the
 * catalogue; for them the sidebar only shows on their settings pages.
 */
export function AppSidebar() {
    const role = usePage().props.auth.user?.role;
    const groups =
        role === 'admin'
            ? adminGroups
            : role === 'encoder'
              ? encoderGroups
              : [];

    return (
        <Sidebar collapsible="icon" className="border-r-sky-700">
            <SidebarHeader className="h-16 justify-center border-b border-sidebar-border px-3 py-0 transition-[height] ease-linear group-data-[collapsible=icon]:h-12 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            className="h-11 hover:bg-sidebar-accent"
                            asChild
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo tone="white" />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-6 py-5">
                {groups.map((group) => (
                    <NavMain
                        key={group.label}
                        label={group.label}
                        items={group.items}
                    />
                ))}

                {/* Staff: the way out to the public site, kept apart from the work pages. */}
                <NavMain
                    items={[catalogueItem]}
                    className={groups.length > 0 ? 'mt-auto' : undefined}
                />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border p-3">
                <NavUser />
            </SidebarFooter>

            <SidebarRail />
        </Sidebar>
    );
}
