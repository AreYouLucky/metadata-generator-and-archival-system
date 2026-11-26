import { cn } from '@/lib/utils';

export type WatchTab = 'about' | 'scenes';

type Props = {
    active: WatchTab;
    tabs: { value: WatchTab; label: string; count?: number }[];
    onChange: (tab: WatchTab) => void;
};

/**
 * Switches the panel under the player between the record's details and the
 * scene (key frame) table, so the page stays short.
 */
export default function WatchTabBar({ active, tabs, onChange }: Props) {
    return (
        <div
            role="tablist"
            aria-label="Record"
            className="flex gap-1 overflow-hidden border-y bg-muted/30 px-4 sm:px-6"
        >
            {tabs.map((tab) => (
                <button
                    key={tab.value}
                    type="button"
                    role="tab"
                    id={`watch-tab-${tab.value}`}
                    aria-selected={active === tab.value}
                    aria-controls={`watch-panel-${tab.value}`}
                    onClick={() => onChange(tab.value)}
                    className={cn(
                        'shrink-0 border-b-2 px-4 py-3 text-sm transition',
                        active === tab.value
                            ? 'border-brand font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                    {tab.count !== undefined && (
                        <span className="ml-1.5 text-xs text-muted-foreground">
                            {tab.count}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
