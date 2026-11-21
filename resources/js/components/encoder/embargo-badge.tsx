import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utilities';

/**
 * Shown while an embargo is in force: the record can't leave the archive
 * (public catalogue, YouTube, API, ZIPs) until that date.
 */
export default function EmbargoBadge({ until }: { until: string }) {
    return (
        <Badge
            variant="outline"
            className="gap-1 border-amber-500/50 text-amber-700 dark:text-amber-200"
            title="Embargoed: not distributed before this date"
        >
            <Lock className="size-3" aria-hidden />
            Embargo until {formatDate(until)}
        </Badge>
    );
}
