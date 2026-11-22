import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Publication } from '@/types/publishing';

type Props = {
    publication: Pick<Publication, 'status' | 'percent'>;
};

const LABELS: Record<Publication['status'], string> = {
    queued: 'Queued',
    uploading: 'Uploading',
    processing: 'Processing on YouTube',
    published: 'Published',
    failed: 'Failed',
};

const STYLES: Record<Publication['status'], string> = {
    queued: 'bg-muted text-muted-foreground',
    uploading: 'bg-secondary/20 text-sky-800',
    processing: 'bg-tertiary/30 text-yellow-900',
    published: 'bg-emerald-500/15 text-emerald-800',
    failed: 'bg-destructive/10 text-destructive',
};

/** Where a YouTube publication stands; the upload shows its percentage. */
export default function PublicationStatusBadge({ publication }: Props) {
    return (
        <Badge
            variant="outline"
            className={cn('border-transparent', STYLES[publication.status])}
        >
            {LABELS[publication.status]}
            {publication.status === 'uploading' && ` ${publication.percent}%`}
        </Badge>
    );
}
