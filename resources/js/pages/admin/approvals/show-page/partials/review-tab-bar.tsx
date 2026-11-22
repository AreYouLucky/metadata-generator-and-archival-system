import { cn } from '@/lib/utils';

export type ReviewTab =
    | 'overview'
    | 'transcript'
    | 'metadata'
    | 'archive'
    | 'visuals'
    | 'translations';

type Props = {
    active: ReviewTab;
    counts: Partial<Record<ReviewTab, number>>;
    onChange: (tab: ReviewTab) => void;
};

const TABS: { value: ReviewTab; label: string }[] = [
    { value: 'overview', label: 'Overview' },
    { value: 'transcript', label: 'Transcript' },
    { value: 'metadata', label: 'Metadata' },
    { value: 'archive', label: 'Archive' },
    { value: 'visuals', label: 'Visuals' },
    { value: 'translations', label: 'Translations' },
];

/**
 * Switches between the review page's tables: the record overview, the
 * transcript, the AI metadata, the archival record, the key frames and the
 * translations.
 */
export default function ReviewTabBar({ active, counts, onChange }: Props) {
    return (
        <div
            role="tablist"
            aria-label="Record"
            className="flex gap-1 overflow-x-auto border-b"
        >
            {TABS.map((tab) => (
                <button
                    key={tab.value}
                    type="button"
                    role="tab"
                    aria-selected={active === tab.value}
                    onClick={() => onChange(tab.value)}
                    className={cn(
                        '-mb-px shrink-0 border-b-2 px-3 py-2 text-sm transition',
                        active === tab.value
                            ? 'border-brand font-medium text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                    {counts[tab.value] !== undefined && (
                        <span className="ml-1.5 text-xs text-muted-foreground">
                            {counts[tab.value]}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
