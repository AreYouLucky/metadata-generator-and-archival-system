import { KeyRound } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useRegenerateWebhookSecret } from '@/hooks/use-api-tokens';
import { getErrorMessage } from '@/lib/utilities';
import SecretReveal from './secret-reveal';

type Props = {
    hasSecret: boolean;
};

/**
 * The secret that signs webhooks (`X-Archive-Signature`). Making a new one
 * replaces the old one at once, so receivers must be updated.
 */
export default function WebhookSecretCard({ hasSecret }: Props) {
    const regenerate = useRegenerateWebhookSecret();

    return (
        <section className="grid gap-3 rounded-lg border p-4">
            <div className="flex items-start gap-3">
                <KeyRound
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
                <div className="grid flex-1 gap-1">
                    <h3 className="text-sm font-medium">
                        Webhook signing secret
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {hasSecret
                            ? 'Set. Webhooks carry X-Archive-Signature: sha256=HMAC(secret, timestamp + "." + body), with the timestamp in X-Archive-Timestamp.'
                            : 'Needed before a record can ask for a webhook_url.'}
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={regenerate.isPending}
                    onClick={() => {
                        if (
                            !hasSecret ||
                            window.confirm(
                                'Make a new secret? The current one stops working at once.',
                            )
                        ) {
                            regenerate.mutate(undefined, {
                                onError: (error) =>
                                    toast.error(getErrorMessage(error)),
                            });
                        }
                    }}
                >
                    {regenerate.isPending && <Spinner />}
                    {hasSecret ? 'Replace' : 'Create'}
                </Button>
            </div>
            {regenerate.data && (
                <SecretReveal
                    label="Your webhook secret"
                    value={regenerate.data}
                />
            )}
        </section>
    );
}
