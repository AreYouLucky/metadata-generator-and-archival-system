import { Sparkles } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useGenerateTitles } from '@/hooks/use-generate-titles';
import { getErrorMessage } from '@/lib/utilities';

type Props = {
    recordId: string;
    metadataId: number;
    /** Waiting for the `record.titles-generated` event. */
    pending: boolean;
    onQueued: () => void;
    disabled: boolean;
};

/**
 * "Generate more": three new title options, written from the summary alone
 * (a few hundred tokens). They appear in the list when they arrive.
 */
export default function GenerateTitlesButton({
    recordId,
    metadataId,
    pending,
    onQueued,
    disabled,
}: Props) {
    const generate = useGenerateTitles(recordId);
    const busy = pending || generate.isPending;

    return (
        <Button
            type="button"
            size="sm"
            variant="ghost"
            className="h-7 text-xs"
            disabled={disabled || busy}
            onClick={() =>
                generate.mutate(metadataId, {
                    onSuccess: onQueued,
                    onError: (error) =>
                        toast.error(
                            getErrorMessage(
                                error,
                                'More titles could not be requested.',
                            ),
                        ),
                })
            }
        >
            {busy ? <Spinner /> : <Sparkles aria-hidden />}
            {busy ? 'Writing titles…' : 'Generate more'}
        </Button>
    );
}
