import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Globe, ShieldAlert } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import ReopenRecordButton from '@/components/admin/reopen-record-button';
import ReviewHistory from '@/components/all/review-history';
import ReviewStatusBadge from '@/components/all/review-status-badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, languageName } from '@/lib/utilities';
import { index, show } from '@/routes/admin/approvals';
import { captions, show as workspace } from '@/routes/records';
import type { Archive } from '@/types/archive';
import type {
    ActiveMetadata,
    Speaker,
    Translation,
    WorkspaceTranscript,
} from '@/types/records';
import type {
    ApprovalRecord,
    FaceSummary,
    ReviewHistoryItem,
} from '@/types/reviews';
import RecordSummary from './partials/record-summary';
import ReviewArchive from './partials/review-archive';
import ReviewDecisionForm from './partials/review-decision-form';
import ReviewFaces from './partials/review-faces';
import ReviewMetadata from './partials/review-metadata';
import ReviewTabBar from './partials/review-tab-bar';
import type { ReviewTab } from './partials/review-tab-bar';
import ReviewTranscript from './partials/review-transcript';
import ReviewTranslations from './partials/review-translations';

type Props = {
    record: ApprovalRecord;
    media: { url: string; playable: boolean };
    transcript: WorkspaceTranscript | null;
    speakers: Speaker[];
    metadata: ActiveMetadata | null;
    translations: Translation[];
    archive: Archive;
    /** What would still keep the record off the public site once approved. */
    publicBlockers: string[];
    /** People on screen (face counts only); null when never counted. */
    faces: FaceSummary | null;
    history: ReviewHistoryItem[];
    canReview: boolean;
    canReopen: boolean;
};

/**
 * Review one submitted record: watch it (with captions), go through
 * everything the encoder did in the workspace as tables (overview,
 * transcript, AI metadata, archive record, translations), read the review
 * history, then approve, request changes or reject with notes.
 */
export default function ApprovalShow({
    record,
    media,
    transcript,
    speakers,
    metadata,
    translations,
    archive,
    publicBlockers,
    faces,
    history,
    canReview,
    canReopen,
}: Props) {
    const video = useRef<HTMLVideoElement>(null);
    const [currentTime, setCurrentTime] = useState(0);
    const [tab, setTab] = useState<ReviewTab>('overview');
    const captionLanguages = [
        ...(transcript && transcript.segments.length > 0
            ? [transcript.language]
            : []),
        ...translations
            .filter((item) => item.status === 'done')
            .map((item) => item.language),
    ];

    const seek = useCallback((seconds: number) => {
        if (!video.current) {
            return;
        }

        video.current.currentTime = seconds;
        void video.current.play().catch(() => undefined);
    }, []);

    return (
        <>
            <Head title={`Review: ${record.title}`} />
            <div className="grid flex-1 gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div className="grid min-w-0 content-start gap-6">
                    <div className="overflow-hidden rounded-xl border bg-black">
                        {media.playable ? (
                            <video
                                ref={video}
                                src={media.url}
                                controls
                                preload="metadata"
                                className="aspect-video max-h-[28rem] w-full"
                                onTimeUpdate={(event) =>
                                    setCurrentTime(
                                        event.currentTarget.currentTime,
                                    )
                                }
                            >
                                {captionLanguages.map((language) => (
                                    <track
                                        key={language}
                                        kind="captions"
                                        src={captions.url([
                                            record.id,
                                            language,
                                        ])}
                                        srcLang={language}
                                        label={languageName(language)}
                                    />
                                ))}
                            </video>
                        ) : (
                            <p className="flex aspect-video items-center justify-center text-sm text-neutral-400">
                                No playable file yet.
                            </p>
                        )}
                    </div>

                    <ReviewTabBar
                        active={tab}
                        onChange={setTab}
                        counts={{
                            transcript: transcript?.segments.length ?? 0,
                            translations: translations.length,
                        }}
                    />

                    {tab === 'overview' && <RecordSummary record={record} />}
                    {tab === 'transcript' && (
                        <ReviewTranscript
                            transcript={transcript}
                            speakers={speakers}
                            currentTime={currentTime}
                            onSeek={seek}
                        />
                    )}
                    {tab === 'metadata' && (
                        <ReviewMetadata metadata={metadata} onSeek={seek} />
                    )}
                    {tab === 'archive' && (
                        <ReviewArchive archive={archive} record={record} />
                    )}
                    {tab === 'translations' && (
                        <ReviewTranslations
                            recordId={record.id}
                            translations={translations}
                            onSeek={seek}
                        />
                    )}
                </div>

                <aside className="grid content-start gap-6 xl:sticky xl:top-4 xl:self-start">
                    <section className="grid gap-3 rounded-xl border p-4">
                        <div className="flex items-center justify-between gap-2">
                            <ReviewStatusBadge status={record.review_status} />
                            <span className="text-xs text-muted-foreground">
                                Submitted {formatDateTime(record.submitted_at)}
                            </span>
                        </div>

                        {publicBlockers.length === 0 ? (
                            <p className="flex gap-2 text-sm text-emerald-700 dark:text-emerald-300">
                                <Globe
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden
                                />
                                Approving publishes it to the public catalogue.
                            </p>
                        ) : (
                            <div className="flex gap-2 text-sm text-amber-700 dark:text-amber-300">
                                <ShieldAlert
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden
                                />
                                <div>
                                    <p>
                                        Approving keeps it in the archive but
                                        off the public catalogue:
                                    </p>
                                    <ul className="mt-1 list-disc pl-4">
                                        {publicBlockers.map((reason) => (
                                            <li key={reason}>{reason}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        )}

                        {canReview ? (
                            <ReviewDecisionForm recordId={record.id} />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                This record isn&apos;t waiting for review.
                            </p>
                        )}

                        <div className="flex flex-wrap gap-2">
                            {canReopen && (
                                <ReopenRecordButton
                                    recordId={record.id}
                                    reloadProps={[
                                        'record',
                                        'history',
                                        'canReview',
                                        'canReopen',
                                        'publicBlockers',
                                    ]}
                                />
                            )}
                            <Button size="sm" variant="outline" asChild>
                                <Link href={workspace(record.id)}>
                                    <ExternalLink aria-hidden />
                                    Open in workspace
                                </Link>
                            </Button>
                        </div>
                    </section>

                    {faces && <ReviewFaces faces={faces} onSeek={seek} />}

                    <section className="grid gap-3 rounded-xl border p-4">
                        <h2 className="text-sm font-medium">Review history</h2>
                        <ReviewHistory history={history} />
                    </section>
                </aside>
            </div>
        </>
    );
}

ApprovalShow.layout = ({ record }: Props) => ({
    breadcrumbs: [
        { title: 'Approvals', href: index() },
        { title: record.title, href: show(record.id) },
    ],
});
