import { CheckCheck } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useConfirmSuggestions } from '@/hooks/use-confirm-suggestions';
import { getErrorMessage } from '@/lib/utilities';

type Props = {
    recordId: string;
    /** Unconfirmed subjects plus keywords. */
    pending: number;
    canEdit: boolean;
};

/**
 * Above Subjects and Keywords while AI suggestions wait: saving the form
 * doesn't confirm them, so this says so and confirms them all in one click.
 */
export default function SuggestionsBanner({
    recordId,
    pending,
    canEdit,
}: Props) {
    const confirmAll = useConfirmSuggestions(recordId, ['archive']);

    if (pending === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand/40 bg-brand/10 px-3 py-2 text-xs">
            <p>
                {pending} AI suggestion{pending === 1 ? '' : 's'} below{' '}
                {pending === 1 ? 'is' : 'are'} not confirmed. Tick each one, or
                confirm them all; unconfirmed ones are never public.
            </p>
            {canEdit && (
                <Button
                    size="sm"
                    variant="secondary"
                    className="h-7 text-xs"
                    disabled={confirmAll.isPending}
                    onClick={() =>
                        confirmAll.mutate(undefined, {
                            onSuccess: (data) =>
                                toast.success(
                                    `Confirmed ${data.confirmed} suggestion${data.confirmed === 1 ? '' : 's'}.`,
                                ),
                            onError: (error) =>
                                toast.error(getErrorMessage(error)),
                        })
                    }
                >
                    {confirmAll.isPending ? (
                        <Spinner />
                    ) : (
                        <CheckCheck aria-hidden />
                    )}
                    Confirm all
                </Button>
            )}
        </div>
    );
}
