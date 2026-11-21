import type { ReactNode } from 'react';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { InspectorTab } from '@/stores/workspace-store';

type Props = {
    panels: Record<InspectorTab, ReactNode>;
};

const ORDER: InspectorTab[] = [
    'metadata',
    'archive',
    'chapters',
    'credits',
    'translations',
    'export',
];

/**
 * Shows the inspector panel for the selected tab (the tabs themselves sit in
 * each panel's header). All stay mounted, so a half-typed credit or an open
 * dropdown survives a switch.
 */
export default function InspectorTabs({ panels }: Props) {
    const tab = useWorkspaceStore((state) => state.inspectorTab);

    return (
        <>
            {ORDER.map((value) => (
                <div
                    key={value}
                    role="tabpanel"
                    hidden={tab !== value}
                    className="h-full"
                >
                    {panels[value]}
                </div>
            ))}
        </>
    );
}
