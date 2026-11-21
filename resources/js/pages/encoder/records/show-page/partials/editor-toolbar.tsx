import { Link } from '@inertiajs/react';
import { Download, Film, Languages } from 'lucide-react';
import ProcessingStatusBadge from '@/components/encoder/processing-status-badge';
import ReviewStatusBadge from '@/components/encoder/review-status-badge';
import { Button } from '@/components/ui/button';
import { show as showProject } from '@/routes/projects';
import { exportMethod } from '@/routes/records';
import type { ProcessingStatus, WorkspaceRecord } from '@/types/records';

type Props = {
    record: WorkspaceRecord;
    processingStatus: ProcessingStatus;
};

/**
 * The editor's top bar: project title, status and export.
 */
export default function EditorToolbar({ record, processingStatus }: Props) {
    return (
        <header className="flex min-h-12 flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-neutral-900 px-3 py-2">
            <div className="flex min-w-0 items-center gap-3">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-brand text-brand-foreground">
                    <Film className="size-4" aria-hidden />
                </span>
                <div className="min-w-0">
                    {record.project && (
                        <Link
                            href={showProject(record.project.id)}
                            className="block truncate text-[11px] text-neutral-400 hover:text-neutral-200 hover:underline"
                        >
                            {record.project.title}
                            {record.series && ` › ${record.series}`}
                        </Link>
                    )}
                    <h1 className="truncate text-sm font-semibold">
                        {record.title}
                    </h1>
                </div>
                <div className="hidden items-center gap-2 sm:flex">
                    <ProcessingStatusBadge status={processingStatus} />
                    <ReviewStatusBadge status={record.review_status} />
                    {record.language && (
                        <span
                            className="flex items-center gap-1 text-xs text-neutral-400"
                            title="Detected language"
                        >
                            <Languages className="size-3.5" aria-hidden />
                            {record.language}
                            {record.language_confidence !== null &&
                                ` ${Math.round(record.language_confidence * 100)}%`}
                        </span>
                    )}
                    {!record.can_edit && (
                        <span className="text-xs text-neutral-400">
                            Read-only
                        </span>
                    )}
                </div>
            </div>
            <Button size="sm" asChild>
                {/* A plain link: the browser handles the download. */}
                <a
                    href={exportMethod.url(record.id, {
                        query: { format: 'json' },
                    })}
                    download
                >
                    <Download aria-hidden />
                    Export JSON
                </a>
            </Button>
        </header>
    );
}
