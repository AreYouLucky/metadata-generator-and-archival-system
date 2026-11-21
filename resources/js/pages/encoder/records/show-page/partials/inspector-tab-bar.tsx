import { useDraftChapters } from '@/hooks/use-metadata-draft';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { InspectorTab } from '@/stores/workspace-store';
import type { ActiveMetadata } from '@/types/records';

const TABS: { value: InspectorTab; label: string }[] = [
    { value: 'metadata', label: 'Metadata' },
    { value: 'chapters', label: 'Chapters' },
    { value: 'credits', label: 'Credits' },
];

type Props = {
    /** Its draft chapters are counted live (they change on the timeline). */
    metadata: ActiveMetadata | null;
    creditCount: number;
};

/**
 * The inspector's tabs, sitting in the panel header's top-left corner in
 * place of a title, with a count on Chapters and Credits.
 */
export default function InspectorTabBar({ metadata, creditCount }: Props) {
    const chapterCount = useDraftChapters(metadata).length;
    const tab = useWorkspaceStore((state) => state.inspectorTab);
    const setTab = useWorkspaceStore((state) => state.setInspectorTab);
    const counts: Partial<Record<InspectorTab, number>> = {
        chapters: chapterCount,
        credits: creditCount,
    };

    return (
        <div
            role="tablist"
            aria-label="Inspector"
            className="-ml-1 flex h-full min-w-0 items-stretch gap-0.5"
        >
            {TABS.map(({ value, label }) => (
                <button
                    key={value}
                    type="button"
                    role="tab"
                    aria-selected={tab === value}
                    onClick={() => setTab(value)}
                    className={cn(
                        '-mb-px flex items-center gap-1.5 border-b-2 px-2 text-[11px] font-semibold tracking-wider whitespace-nowrap uppercase transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        tab === value
                            ? 'border-brand text-foreground'
                            : 'border-transparent text-neutral-500 hover:text-neutral-300',
                    )}
                >
                    {label}
                    {(counts[value] ?? 0) > 0 && (
                        <span className="text-neutral-500 tabular-nums">
                            {counts[value]}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
