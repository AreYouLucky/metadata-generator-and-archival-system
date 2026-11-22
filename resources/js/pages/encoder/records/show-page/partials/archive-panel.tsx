import type { ReactNode } from 'react';
import { useState } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import { cn } from '@/lib/utils';
import type {
    Archive,
    ArchiveOptions,
    SuggestedLocation,
} from '@/types/archive';
import ArchiveArchival from './archive-archival';
import ArchiveCredits from './archive-credits';
import ArchiveDescriptive from './archive-descriptive';
import ArchiveLocation from './archive-location';
import ArchiveProvenance from './archive-provenance';
import ArchiveRights from './archive-rights';
import ArchiveStructure from './archive-structure';
import ArchiveTechnical from './archive-technical';

type Props = {
    recordId: string;
    heading: ReactNode;
    archive: Archive;
    options: ArchiveOptions;
    suggestedLocation: SuggestedLocation | null;
    canEdit: boolean;
};

const SECTIONS = [
    ['descriptive', 'Descriptive'],
    ['location', 'Location'],
    ['credits', 'Credits'],
    ['technical', 'Technical'],
    ['rights', 'Rights'],
    ['provenance', 'Provenance'],
    ['archival', 'Archival'],
    ['structure', 'Structure'],
] as const;

type Section = (typeof SECTIONS)[number][0];

/**
 * The catalogue record (PROJECT_SPEC §11) in eight sections: what it is,
 * where, who made it, how it is encoded, who owns it, what has happened to
 * it, how it is kept, and how the pieces relate.
 */
export default function ArchivePanel({
    recordId,
    heading,
    archive,
    options,
    suggestedLocation,
    canEdit,
}: Props) {
    const [section, setSection] = useState<Section>('descriptive');
    const pendingSuggestions =
        archive.subjects.filter((subject) => !subject.is_confirmed).length +
        archive.keywords.filter((keyword) => !keyword.is_confirmed).length;
    const props = { recordId, archive, options, canEdit };

    return (
        <EditorPanel title="Archive" heading={heading}>
            <nav
                aria-label="Archive sections"
                className="flex shrink-0 flex-wrap gap-1 border-b border-border px-3 py-2"
            >
                {SECTIONS.map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        aria-pressed={section === value}
                        onClick={() => setSection(value)}
                        className={cn(
                            'rounded-full px-2.5 py-1 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            section === value
                                ? 'bg-brand text-brand-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
                    >
                        {label}
                        {value === 'descriptive' && pendingSuggestions > 0 && (
                            <span className="ml-1 tabular-nums opacity-80">
                                {pendingSuggestions}
                            </span>
                        )}
                    </button>
                ))}
            </nav>
            <div className="@container min-h-0 flex-1 overflow-y-auto p-5">
                {section === 'descriptive' && <ArchiveDescriptive {...props} />}
                {section === 'location' && (
                    <ArchiveLocation
                        recordId={recordId}
                        archive={archive}
                        suggestion={suggestedLocation}
                        canEdit={canEdit}
                    />
                )}
                {section === 'credits' && (
                    <ArchiveCredits
                        recordId={recordId}
                        archive={archive}
                        canEdit={canEdit}
                    />
                )}
                {section === 'technical' && (
                    <ArchiveTechnical
                        recordId={recordId}
                        archive={archive}
                        canEdit={canEdit}
                    />
                )}
                {section === 'rights' && <ArchiveRights {...props} />}
                {section === 'provenance' && <ArchiveProvenance {...props} />}
                {section === 'archival' && <ArchiveArchival {...props} />}
                {section === 'structure' && <ArchiveStructure {...props} />}
            </div>
        </EditorPanel>
    );
}
