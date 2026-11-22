import type { ReactNode } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import { useDraftChapters } from '@/hooks/use-metadata-draft';
import type { Clip, ClipLimits } from '@/types/clips';
import type { ActiveMetadata } from '@/types/records';
import ClipForm from './clip-form';
import ClipRow from './clip-row';

type Props = {
    recordId: string;
    heading: ReactNode;
    clips: Clip[];
    limits: ClipLimits;
    metadata: ActiveMetadata | null;
    duration: number;
};

/**
 * Clips (Phase 7): mark a range and cut it from the master to its own MP4
 * (checksummed and probed like any file), then play, download or delete.
 */
export default function ClipsPanel({
    recordId,
    heading,
    clips,
    limits,
    metadata,
    duration,
}: Props) {
    const chapters = useDraftChapters(metadata);

    return (
        <EditorPanel title="Clips" heading={heading}>
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
                <ClipForm
                    recordId={recordId}
                    chapters={chapters}
                    duration={duration}
                    limits={limits}
                />
                <p className="text-xs text-muted-foreground">
                    Tip: press I and O at the playhead, Shift-click transcript
                    lines, or use a key quote’s Clip button.
                </p>
                {clips.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No clips yet.
                    </p>
                ) : (
                    <ul className="grid gap-2">
                        {clips.map((clip) => (
                            <ClipRow
                                key={clip.id}
                                recordId={recordId}
                                clip={clip}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </EditorPanel>
    );
}
