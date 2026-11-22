import { CheckCheck } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useConfirmSuggestions } from '@/hooks/use-confirm-suggestions';
import { getErrorMessage } from '@/lib/utilities';

/**
 * Admins: confirm every pending AI subject and keyword suggestion from the
 * review page, then refresh its tables.
 */
export default function ConfirmSuggestionsButton({
    recordId,
}: {
    recordId: string;
}) {
    const confirmAll = useConfirmSuggestions(recordId, ['record', 'archive']);

    return (
        <Button
            size="sm"
            variant="outline"
            className="ml-2 h-7"
            disabled={confirmAll.isPending}
            onClick={() =>
                confirmAll.mutate(undefined, {
                    onSuccess: (data) =>
                        toast.success(
                            `Confirmed ${data.confirmed} suggestion${data.confirmed === 1 ? '' : 's'}.`,
                        ),
                    onError: (error) => toast.error(getErrorMessage(error)),
                })
            }
        >
            {confirmAll.isPending ? <Spinner /> : <CheckCheck aria-hidden />}
            Confirm all
        </Button>
    );
}
