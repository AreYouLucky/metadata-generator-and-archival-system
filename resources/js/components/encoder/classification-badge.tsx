import { ShieldAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatEnumLabel } from '@/lib/utilities';

/**
 * A security classification above unclassified: the record is visible to its
 * creator and admins only, and never public. Unclassified shows nothing.
 */
export default function ClassificationBadge({
    classification,
}: {
    classification: string;
}) {
    if (classification === 'unclassified') {
        return null;
    }

    return (
        <Badge
            variant="outline"
            className="gap-1 border-red-500/50 text-red-700 dark:text-red-200"
            title="Classified: never distributed"
        >
            <ShieldAlert className="size-3" aria-hidden />
            {formatEnumLabel(classification)}
        </Badge>
    );
}
