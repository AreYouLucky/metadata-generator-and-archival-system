import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Loader2,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ProcessingStatus } from '@/types/records';

const STYLES: Record<
    ProcessingStatus,
    { label: string; icon: typeof Clock; className: string }
> = {
    queued: {
        label: 'Queued',
        icon: Clock,
        className: 'bg-muted text-muted-foreground',
    },
    processing: {
        label: 'Processing',
        icon: Loader2,
        className: 'bg-brand-muted text-brand [&>svg]:animate-spin',
    },
    ready: {
        label: 'Ready',
        icon: CheckCircle2,
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    failed: {
        label: 'Failed',
        icon: AlertTriangle,
        className: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    },
    cancelled: {
        label: 'Cancelled',
        icon: XCircle,
        className: 'bg-muted text-muted-foreground',
    },
};

export default function ProcessingStatusBadge({
    status,
    className,
}: {
    status: ProcessingStatus;
    className?: string;
}) {
    const { label, icon: Icon, className: tone } = STYLES[status];

    return (
        <Badge
            variant="outline"
            className={cn('border-transparent', tone, className)}
        >
            <Icon aria-hidden />
            {label}
        </Badge>
    );
}
