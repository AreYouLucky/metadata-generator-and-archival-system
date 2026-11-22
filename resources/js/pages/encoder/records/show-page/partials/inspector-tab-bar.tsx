import { useEffect, useRef, useState } from 'react';
import { useDraftChapters } from '@/hooks/use-metadata-draft';
import { cn } from '@/lib/utils';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { InspectorTab } from '@/stores/workspace-store';
import type { ActiveMetadata } from '@/types/records';
import TabScrollButton from './tab-scroll-button';

const TABS: { value: InspectorTab; label: string }[] = [
    { value: 'metadata', label: 'Metadata' },
    { value: 'archive', label: 'Archive' },
    { value: 'visuals', label: 'Visuals' },
    { value: 'chapters', label: 'Chapters' },
    { value: 'credits', label: 'Credits' },
    { value: 'translations', label: 'Languages' },
    { value: 'clips', label: 'Clips' },
];

type Props = {
    /** Its draft chapters are counted live (they change on the timeline). */
    metadata: ActiveMetadata | null;
    creditCount: number;
    translationCount: number;
    /** Unconfirmed AI subjects and keywords. */
    suggestionCount: number;
    frameCount: number;
    clipCount: number;
};

/**
 * The inspector's tabs, sitting in the panel header's top-left corner in
 * place of a title, with counts. One row that scrolls sideways and snaps to
 * a tab when the inspector is too narrow for all of them; arrows appear on
 * the side where tabs are hidden, and the open tab is always scrolled into
 * view. Export isn't a tab: the header's Export button opens it.
 */
export default function InspectorTabBar({
    metadata,
    creditCount,
    translationCount,
    suggestionCount,
    frameCount,
    clipCount,
}: Props) {
    const chapterCount = useDraftChapters(metadata).length;
    const tab = useWorkspaceStore((state) => state.inspectorTab);
    const setTab = useWorkspaceStore((state) => state.setInspectorTab);
    const strip = useRef<HTMLDivElement>(null);
    const [hidden, setHidden] = useState({ left: false, right: false });
    const counts: Partial<Record<InspectorTab, number>> = {
        chapters: chapterCount,
        credits: creditCount,
        translations: translationCount,
        archive: suggestionCount,
        visuals: frameCount,
        clips: clipCount,
    };

    // Which ends have tabs out of view. Also runs when a hidden panel's copy
    // of the bar is shown (its size changes from zero).
    useEffect(() => {
        const element = strip.current;

        if (!element) {
            return;
        }

        const update = () =>
            setHidden({
                left: element.scrollLeft > 1,
                right:
                    element.scrollLeft + element.clientWidth <
                    element.scrollWidth - 1,
            });
        update();
        element.addEventListener('scroll', update, { passive: true });
        const observer = new ResizeObserver(update);
        observer.observe(element);

        return () => {
            element.removeEventListener('scroll', update);
            observer.disconnect();
        };
    }, []);

    // Keep the open tab in view, scrolling only the strip (never the page).
    useEffect(() => {
        const element = strip.current;
        const button = element?.querySelector<HTMLElement>(
            `[data-tab="${tab}"]`,
        );

        if (!element || !button) {
            return;
        }

        const start = button.offsetLeft;
        const end = start + button.offsetWidth;

        if (start < element.scrollLeft) {
            element.scrollTo({ left: start, behavior: 'smooth' });
        } else if (end > element.scrollLeft + element.clientWidth) {
            element.scrollTo({
                left: end - element.clientWidth,
                behavior: 'smooth',
            });
        }
    }, [tab]);

    const page = (direction: -1 | 1) =>
        strip.current?.scrollBy({
            left: direction * strip.current.clientWidth * 0.6,
            behavior: 'smooth',
        });

    return (
        <div className="relative -ml-1 flex h-full min-w-0 flex-1">
            {hidden.left && (
                <TabScrollButton direction="left" onClick={() => page(-1)} />
            )}
            <div
                ref={strip}
                role="tablist"
                aria-label="Inspector"
                className="relative flex h-full min-w-0 flex-1 snap-x snap-mandatory [scrollbar-width:none] items-stretch gap-0.5 overflow-x-auto overscroll-x-contain [&::-webkit-scrollbar]:hidden"
            >
                {TABS.map(({ value, label }) => (
                    <button
                        key={value}
                        type="button"
                        role="tab"
                        data-tab={value}
                        aria-selected={tab === value}
                        onClick={() => setTab(value)}
                        className={cn(
                            '-mb-px flex shrink-0 snap-start items-center gap-1.5 border-b-2 px-2 text-[11px] font-semibold tracking-wider whitespace-nowrap uppercase transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                            tab === value
                                ? 'border-brand text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {label}
                        {(counts[value] ?? 0) > 0 && (
                            <span className="text-muted-foreground tabular-nums">
                                {counts[value]}
                            </span>
                        )}
                    </button>
                ))}
            </div>
            {hidden.right && (
                <TabScrollButton direction="right" onClick={() => page(1)} />
            )}
        </div>
    );
}
