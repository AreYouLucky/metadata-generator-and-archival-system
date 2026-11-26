import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { FileVideo, Upload } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index as records } from '@/routes/records';
import type {
    DashboardActivityDay,
    DashboardAllowance,
    DashboardAttentionItem,
    DashboardDraftItem,
    DashboardProcessingItem,
    DashboardProjectItem,
    DashboardStats,
} from '@/types/dashboard';
import ActivityChart from './partials/activity-chart';
import AttentionList from './partials/attention-list';
import DraftList from './partials/draft-list';
import ProcessingAllowance from './partials/processing-allowance';
import ProcessingList from './partials/processing-list';
import ProjectList from './partials/project-list';
import ReviewPipeline from './partials/review-pipeline';
import StatTiles from './partials/stat-tiles';

type Props = {
    isAdmin: boolean;
    stats: DashboardStats;
    activity: DashboardActivityDay[];
    attention: DashboardAttentionItem[];
    processing: DashboardProcessingItem[];
    drafts: DashboardDraftItem[];
    projects: DashboardProjectItem[];
    allowance: DashboardAllowance;
};

/** How often the processing section refreshes while something is running. */
const POLL_MS = 10_000;

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Good morning';
    }

    return hour < 18 ? 'Good afternoon' : 'Good evening';
}

/**
 * The encoder and admin home: headline numbers, what needs attention, what
 * is processing, and where to pick up work. Encoders see their own records;
 * admins see the whole archive.
 */
export default function Dashboard({
    isAdmin,
    stats,
    activity,
    attention,
    processing,
    drafts,
    projects,
    allowance,
}: Props) {
    const name = usePage().props.auth.user?.name?.split(' ')[0];

    // Only the sections a finished stage can change are reloaded, and only
    // while something is in the pipeline.
    const { start, stop } = usePoll(
        POLL_MS,
        { only: ['stats', 'processing', 'attention', 'drafts'] },
        { autoStart: false },
    );
    const isProcessing = processing.length > 0;

    useEffect(() => {
        if (isProcessing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isProcessing, start, stop]);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <header className="space-y-0.5">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {greeting()}
                            {name ? `, ${name}` : ''}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {isAdmin
                                ? 'Everything happening across the archive.'
                                : 'Your uploads, their processing and reviews.'}
                        </p>
                    </header>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={records()}>
                                <FileVideo aria-hidden />
                                All records
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={create()}>
                                <Upload aria-hidden />
                                Upload video
                            </Link>
                        </Button>
                    </div>
                </div>

                <StatTiles stats={stats} isAdmin={isAdmin} />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="grid content-start gap-6 lg:col-span-2">
                        <ActivityChart days={activity} />
                        <ReviewPipeline stats={stats} />
                        <div className="grid gap-6 md:grid-cols-2">
                            <DraftList items={drafts} />
                            <ProjectList items={projects} isAdmin={isAdmin} />
                        </div>
                    </div>

                    <div className="grid content-start gap-6">
                        <AttentionList items={attention} />
                        <ProcessingList items={processing} stats={stats} />
                        {allowance && (
                            <ProcessingAllowance allowance={allowance} />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
