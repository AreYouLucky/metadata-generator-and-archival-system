import { AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatEnumLabel } from '@/lib/utilities';
import type { ContentFlags, Tone } from '@/types/records';

type Props = {
    tone: Tone | null;
    flags: ContentFlags | null;
};

/**
 * The video's tone and content flags (profanity, sensitive topics) as badges,
 * so a reviewer sees them before publishing.
 */
export default function ToneBadges({ tone, flags }: Props) {
    if (!tone && !flags) {
        return null;
    }

    const warnings = [
        ...(flags?.profanity ? ['Profanity'] : []),
        ...(flags?.sensitiveTopics ?? []).map(formatEnumLabel),
    ];

    return (
        <div className="grid gap-2">
            <h3 className="text-sm font-medium">Tone and content</h3>
            <div className="flex flex-wrap gap-1.5">
                {tone && (
                    <Badge variant="secondary">{formatEnumLabel(tone)}</Badge>
                )}
                {warnings.map((warning) => (
                    <Badge
                        key={warning}
                        variant="outline"
                        className="gap-1 border-amber-400/40 text-amber-700"
                    >
                        <AlertTriangle className="size-3" aria-hidden />
                        {warning}
                    </Badge>
                ))}
                {flags && warnings.length === 0 && (
                    <Badge variant="outline" className="text-muted-foreground">
                        No content flags
                    </Badge>
                )}
            </div>
        </div>
    );
}
