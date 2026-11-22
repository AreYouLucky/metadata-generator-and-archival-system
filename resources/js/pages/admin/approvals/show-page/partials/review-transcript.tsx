import { useVirtualizer } from '@tanstack/react-virtual';
import { Search } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import {
    findSegmentIndex,
    formatTimestamp,
    languageName,
    speakerName,
} from '@/lib/utilities';
import type { Speaker, WorkspaceTranscript } from '@/types/records';

type Props = {
    transcript: WorkspaceTranscript | null;
    speakers: Speaker[];
    currentTime: number;
    onSeek: (seconds: number) => void;
};

/**
 * The transcript as a table (time, speaker, text, edited), with a filter,
 * the line playing highlighted and click-to-seek. Virtualised with spacer
 * rows, as long videos have thousands of lines.
 */
export default function ReviewTranscript({
    transcript,
    speakers,
    currentTime,
    onSeek,
}: Props) {
    const [query, setQuery] = useState('');
    const scroller = useRef<HTMLDivElement>(null);
    const segments = useMemo(() => transcript?.segments ?? [], [transcript]);
    const names = useMemo(
        () =>
            new Map(
                speakers.map((speaker) => [speaker.id, speakerName(speaker)]),
            ),
        [speakers],
    );
    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return needle === ''
            ? segments
            : segments.filter((segment) =>
                  segment.text.toLowerCase().includes(needle),
              );
    }, [segments, query]);
    const playing = useMemo(
        () => segments[findSegmentIndex(segments, currentTime)]?.id ?? null,
        [segments, currentTime],
    );
    const virtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: () => scroller.current,
        estimateSize: () => 44,
        overscan: 10,
    });

    if (transcript === null) {
        return (
            <p className="rounded-xl border p-6 text-center text-sm text-muted-foreground">
                No transcript yet.
            </p>
        );
    }

    const items = virtualizer.getVirtualItems();
    const top = items[0]?.start ?? 0;
    const bottom =
        virtualizer.getTotalSize() - (items[items.length - 1]?.end ?? 0);
    const edited = segments.filter((segment) => segment.is_edited).length;

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {languageName(transcript.language)} · {segments.length}{' '}
                    lines · {edited} edited by hand
                </p>
                <div className="relative w-full sm:w-72">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Filter lines"
                        aria-label="Filter transcript lines"
                        className="pl-8"
                    />
                </div>
            </div>
            <div
                ref={scroller}
                className="max-h-[32rem] overflow-auto rounded-xl border"
            >
                <table className="w-full text-sm">
                    <thead className="sticky top-0 z-10 border-b bg-muted text-left text-xs text-muted-foreground">
                        <tr>
                            <th
                                scope="col"
                                className="w-20 px-4 py-2 font-medium"
                            >
                                Time
                            </th>
                            <th
                                scope="col"
                                className="w-36 px-4 py-2 font-medium"
                            >
                                Speaker
                            </th>
                            <th scope="col" className="px-4 py-2 font-medium">
                                Text
                            </th>
                            <th
                                scope="col"
                                className="w-20 px-4 py-2 font-medium"
                            >
                                Edited
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-6 text-center text-muted-foreground"
                                >
                                    No lines match.
                                </td>
                            </tr>
                        )}
                        {top > 0 && <tr aria-hidden style={{ height: top }} />}
                        {items.map((item) => {
                            const segment = rows[item.index];

                            return (
                                <tr
                                    key={segment.id}
                                    data-index={item.index}
                                    ref={virtualizer.measureElement}
                                    className={cn(
                                        'border-b align-top last:border-0',
                                        segment.id === playing &&
                                            'bg-brand-muted',
                                    )}
                                >
                                    <td className="px-4 py-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onSeek(segment.start)
                                            }
                                            className="text-brand tabular-nums hover:underline"
                                            aria-label={`Play from ${formatTimestamp(segment.start)}`}
                                        >
                                            {formatTimestamp(segment.start)}
                                        </button>
                                    </td>
                                    <td className="px-4 py-2 text-muted-foreground">
                                        {segment.speaker_id !== null
                                            ? (names.get(segment.speaker_id) ??
                                              '—')
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-2">
                                        {segment.text}
                                    </td>
                                    <td className="px-4 py-2 text-muted-foreground">
                                        {segment.is_edited ? 'Yes' : 'No'}
                                    </td>
                                </tr>
                            );
                        })}
                        {bottom > 0 && (
                            <tr aria-hidden style={{ height: bottom }} />
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
