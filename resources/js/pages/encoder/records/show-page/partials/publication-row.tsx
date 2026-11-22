import { ExternalLink, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import PublicationStatusBadge from '@/components/encoder/publication-status-badge';
import { Button } from '@/components/ui/button';
import { useCheckYouTubeStatus } from '@/hooks/use-youtube';
import { formatDateTime, getErrorMessage } from '@/lib/utilities';
import type { Publication } from '@/types/publishing';

type Props = {
    recordId: string;
    publication: Publication;
};

/**
 * One publication: status (with upload progress), link to the video,
 * errors, and "Check status" once it is on YouTube.
 */
export default function PublicationRow({ recordId, publication }: Props) {
    const check = useCheckYouTubeStatus(recordId);
    const onYouTube =
        publication.status === 'processing' ||
        publication.status === 'published';

    return (
        <li className="grid gap-1.5 rounded-lg border border-border bg-card p-3">
            <div className="flex items-center gap-2">
                <p className="min-w-0 flex-1 truncate text-sm font-medium">
                    {publication.title}
                </p>
                <PublicationStatusBadge publication={publication} />
            </div>
            {publication.status === 'uploading' && (
                <div
                    className="h-1 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuenow={publication.percent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label="Upload progress"
                >
                    <div
                        className="h-full bg-secondary transition-all"
                        style={{ width: `${publication.percent}%` }}
                    />
                </div>
            )}
            <p className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                <span>{formatDateTime(publication.created_at)}</span>
                <span>
                    {publication.privacy_status} on {publication.channel_title}
                </span>
                {publication.published_by && (
                    <span>by {publication.published_by}</span>
                )}
            </p>
            {publication.error && (
                <p className="text-xs text-destructive">{publication.error}</p>
            )}
            {publication.thumbnail_error && (
                <p className="text-xs text-muted-foreground">
                    {publication.thumbnail_error}
                </p>
            )}
            {onYouTube && (
                <div className="flex flex-wrap gap-2">
                    {publication.video_url && (
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="h-7"
                        >
                            <a
                                href={publication.video_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink aria-hidden />
                                Open on YouTube
                            </a>
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="ghost"
                        className="h-7"
                        disabled={check.isPending}
                        onClick={() =>
                            check.mutate(publication.id, {
                                onSuccess: () =>
                                    toast.success('Asking YouTube…'),
                                onError: (error) =>
                                    toast.error(getErrorMessage(error)),
                            })
                        }
                    >
                        <RefreshCw aria-hidden />
                        Check status
                    </Button>
                </div>
            )}
        </li>
    );
}
