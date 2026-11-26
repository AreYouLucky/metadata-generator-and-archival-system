import { Head } from "@inertiajs/react";
import {
    CalendarDays,
    Captions,
    Languages,
    ListVideo,
    ScrollText,
} from "lucide-react";
import { useCallback, useRef, useState } from "react";
import RecordGrid from "@/components/user/record-grid";
import { useAskMePage } from "@/hooks/use-ask-me-page";
import { useStartTime } from "@/hooks/use-start-time";
import { formatDate, languageName } from "@/lib/utilities";
import type {
    CatalogueCard,
    WatchCaption,
    WatchFrame,
    WatchRecord,
    WatchTranscriptLine,
} from "@/types/catalogue";
import WatchChapters from "./partials/watch-chapters";
import WatchDetails from "./partials/watch-details";
import WatchFrames from "./partials/watch-frames";
import WatchPlayer from "./partials/watch-player";
import WatchTabBar from "./partials/watch-tab-bar";
import type { WatchTab } from "./partials/watch-tab-bar";
import WatchTranscript from "./partials/watch-transcript";

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
 * language, chapters and a synced transcript (click to jump), and tabs under
 * the player for the public catalogue fields and the key frame table. Never a
 * download of the master.
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
    const [player, setPlayer] = useState<HTMLVideoElement | null>(null);
    const [currentTime, setCurrentTime] = useState(0);
    const [tab, setTab] = useState<WatchTab>("about");

    const tabs: { value: WatchTab; label: string; count?: number }[] = [
        { value: "about", label: "About" },
    ];

    if (frames.length > 0) {
        tabs.push({
            value: "scenes",
            label: "Scenes",
            count: frames.length,
        });
    }

    const currentChapter = [...record.chapters]
        .reverse()
        .find((chapter) => chapter.start <= currentTime);

    const activeTab: WatchTab = frames.length > 0 ? tab : "about";

    const seek = useCallback((seconds: number) => {
        if (!video.current) {
            return;
        }

        video.current.currentTime = seconds;
        void video.current.play().catch(() => undefined);
    }, []);

    const attachVideo = useCallback((element: HTMLVideoElement | null) => {
        video.current = element;
        setPlayer(element);
    }, []);

    // "Ask me": this record is the chat's "this video"; ?t= opens a cited moment.
    // A moment picked in the chat also brings the player into view.
    useAskMePage(record.id, (seconds) => {
        seek(seconds);
        video.current?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
    useStartTime(player);

    return (
        <>
            <Head title={record.title} />
            <div className="grid gap-12">
                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_24rem] xl:gap-10">
                    <div className="grid content-start gap-6">
                        <div className="grid gap-3 rounded-2xl border bg-card shadow-sm sm:gap-4">
                            <WatchPlayer
                                recordId={record.id}
                                title={record.title}
                                mediaUrl={mediaUrl}
                                poster={record.thumbnail}
                                captions={captions}
                                chapters={record.chapters}
                                frames={frames}
                                currentChapter={currentChapter?.title ?? null}
                                videoRef={attachVideo}
                                onTimeUpdate={setCurrentTime}
                            />
                        </div>

                        <section className="overflow-hidden rounded-2xl border bg-card shadow-sm">
                            <header className="grid gap-3 px-6 pt-6 pb-5 sm:px-8 sm:pt-8 sm:pb-6">
                                <h1 className="text-2xl font-semibold tracking-tight text-balance">
                                    {record.title}
                                </h1>
                                {record.alternative_title && (
                                    <p className="text-sm text-muted-foreground">
                                        Also known as {record.alternative_title}
                                    </p>
                                )}
                                <div className="mt-1 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground">
                                    {record.published_at && (
                                        <span className="inline-flex items-center gap-1.5">
                                            <CalendarDays className="size-3.5" />
                                            Published{" "}
                                            {formatDate(record.published_at)}
                                        </span>
                                    )}
                                    {record.language && (
                                        <span className="inline-flex items-center gap-1.5">
                                            <Languages className="size-3.5" />
                                            {languageName(record.language)}
                                        </span>
                                    )}
                                    {captions.length > 0 && (
                                        <span className="inline-flex items-center gap-1.5">
                                            <Captions className="size-3.5" />
                                            Captions in {captions.length}{" "}
                                            {captions.length === 1
                                                ? "language"
                                                : "languages"}
                                        </span>
                                    )}
                                </div>
                            </header>

                            <WatchTabBar
                                active={activeTab}
                                tabs={tabs}
                                onChange={setTab}
                            />
                            <div
                                role="tabpanel"
                                id={`watch-panel-${activeTab}`}
                                aria-labelledby={`watch-tab-${activeTab}`}
                            >
                                {activeTab === "about" ? (
                                    <div className="grid gap-8 p-6 sm:p-8">
                                        {record.description && (
                                            <p className="max-w-3xl text-justify text-sm leading-relaxed hyphens-auto whitespace-pre-line">
                                                {record.description}
                                            </p>
                                        )}
                                        <WatchDetails record={record} />
                                    </div>
                                ) : (
                                    <WatchFrames
                                        frames={frames}
                                        onSeek={seek}
                                    />
                                )}
                            </div>
                        </section>
                    </div>

                    <aside className="grid content-start gap-6">
                        {record.chapters.length > 0 && (
                            <section className="overflow-hidden rounded-2xl border bg-card shadow-sm">
                                <h2 className="flex items-center gap-2 border-b px-5 py-4 text-sm font-semibold">
                                    <ListVideo className="size-4 text-muted-foreground" />
                                    Chapters
                                    <span className="ml-auto text-xs font-normal text-muted-foreground">
                                        {record.chapters.length}
                                    </span>
                                </h2>
                                <WatchChapters
                                    chapters={record.chapters}
                                    currentTime={currentTime}
                                    onSeek={seek}
                                />
                            </section>
                        )}
                        <section className="overflow-hidden rounded-2xl border bg-card shadow-sm">
                            <h2 className="flex items-center gap-2 border-b px-5 py-4 text-sm font-semibold">
                                <ScrollText className="size-4 text-muted-foreground" />
                                Transcript
                                {transcript.language && (
                                    <span className="ml-auto text-xs font-normal text-muted-foreground">
                                        {languageName(transcript.language)}
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

                {related.length > 0 && (
                    <section className="grid gap-6 border-t pt-10">
                        <h2 className="text-lg font-semibold tracking-tight">
                            More from {record.project?.title ?? "this event"}
                        </h2>
                        <RecordGrid records={related} empty="" />
                    </section>
                )}
            </div>
        </>
    );
}
