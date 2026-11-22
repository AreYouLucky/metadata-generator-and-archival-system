import { Head } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import RecordGrid from '@/components/user/record-grid';
import { formatDate, languageName } from '@/lib/utilities';
import { captions as captionsRoute } from '@/routes/catalogue';
import type {
    CatalogueCard,
    WatchCaption,
    WatchFrame,
    WatchRecord,
    WatchTranscriptLine,
} from '@/types/catalogue';
import WatchChapters from './partials/watch-chapters';
import WatchDetails from './partials/watch-details';
import WatchFrames from './partials/watch-frames';
import WatchTranscript from './partials/watch-transcript';

type Props = {
    record: WatchRecord;
    transcript: { language: string | null; lines: WatchTranscriptLine[] };
    captions: WatchCaption[];
    frames: WatchFrame[];
    related: CatalogueCard[];
    mediaUrl: string;
};

/**
 * The public player: the access copy with captions in every finished
 * language, chapters and a synced transcript (click to jump), the key frames
 * as a table, and the public catalogue fields. Never a download of the master.
 */
export default function Watch({
    record,
    transcript,
    captions,
    frames,
    related,
    mediaUrl,
}: Props) {
    const video = useRef<HTMLVideoElement>(null);
    const [currentTime, setCurrentTime] = useState(0);

    const seek = useCallback((seconds: number) => {
        if (!video.current) {
            return;
        }

        video.current.currentTime = seconds;
        void video.current.play().catch(() => undefined);
    }, []);

    return (
        <>
            <Head title={record.title} />
            <div className="grid gap-10">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="grid content-start gap-4">
                        <div className="overflow-hidden rounded-xl bg-black">
                            <video
                                ref={video}
                                src={mediaUrl}
                                poster={record.thumbnail ?? undefined}
                                controls
                                controlsList="nodownload"
                                preload="metadata"
                                playsInline
                                className="aspect-video w-full"
                                onTimeUpdate={(event) =>
                                    setCurrentTime(
                                        event.currentTarget.currentTime,
                                    )
                                }
                            >
                                {captions.map((caption) => (
                                    <track
                                        key={caption.language}
                                        kind="captions"
                                        src={captionsRoute.url([
                                            record.id,
                                            caption.language,
                                        ])}
                                        srcLang={caption.language}
                                        label={languageName(caption.language)}
                                    />
                                ))}
                            </video>
                        </div>

                        <div className="grid gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight text-balance">
                                {record.title}
                            </h1>
                            {record.alternative_title && (
                                <p className="text-sm text-muted-foreground">
                                    Also known as {record.alternative_title}
                                </p>
                            )}
                            {record.published_at && (
                                <p className="text-xs text-muted-foreground">
                                    Published {formatDate(record.published_at)}
                                </p>
                            )}
                            {record.description && (
                                <p className="mt-2 max-w-3xl whitespace-pre-line">
                                    {record.description}
                                </p>
                            )}
                        </div>

                        <WatchDetails record={record} />
                    </div>

                    <aside className="grid content-start gap-4">
                        {record.chapters.length > 0 && (
                            <section className="grid gap-2 rounded-xl border p-3">
                                <h2 className="px-1 text-sm font-medium">
                                    Chapters
                                </h2>
                                <WatchChapters
                                    chapters={record.chapters}
                                    currentTime={currentTime}
                                    onSeek={seek}
                                />
                            </section>
                        )}
                        <section className="overflow-hidden rounded-xl border">
                            <h2 className="border-b px-4 py-3 text-sm font-medium">
                                Transcript
                                {transcript.language && (
                                    <span className="font-normal text-muted-foreground">
                                        {' '}
                                        · {languageName(transcript.language)}
                                    </span>
                                )}
                            </h2>
                            <WatchTranscript
                                lines={transcript.lines}
                                currentTime={currentTime}
                                onSeek={seek}
                            />
                        </section>
                    </aside>
                </div>

                {frames.length > 0 && (
                    <section className="grid gap-4">
                        <h2 className="text-xl font-semibold">On screen</h2>
                        <WatchFrames frames={frames} onSeek={seek} />
                    </section>
                )}

                {related.length > 0 && (
                    <section className="grid gap-4">
                        <h2 className="text-xl font-semibold">
                            More from {record.project?.title ?? 'this event'}
                        </h2>
                        <RecordGrid records={related} empty="" />
                    </section>
                )}
            </div>
        </>
    );
}
