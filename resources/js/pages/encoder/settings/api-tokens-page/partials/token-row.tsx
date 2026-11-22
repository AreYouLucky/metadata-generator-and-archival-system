import { Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useRevokeApiToken } from '@/hooks/use-api-tokens';
import { formatDate, formatDateTime, getErrorMessage } from '@/lib/utilities';
import type { ApiToken } from '@/types/api-tokens';

type Props = {
    token: ApiToken;
};

/** One token: name, abilities, last use, expiry and Revoke. */
export default function TokenRow({ token }: Props) {
    const revoke = useRevokeApiToken();

    return (
        <li className="grid gap-2 rounded-lg border p-4">
            <div className="flex items-center gap-2">
                <p className="min-w-0 flex-1 truncate font-medium">
                    {token.name}
                </p>
                {token.expired && <Badge variant="destructive">Expired</Badge>}
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 text-destructive"
                    disabled={revoke.isPending}
                    aria-label={`Revoke ${token.name}`}
                    onClick={() => {
                        if (
                            window.confirm(
                                `Revoke "${token.name}"? Anything using it stops working at once.`,
                            )
                        ) {
                            revoke.mutate(token.id, {
                                onSuccess: () =>
                                    toast.success('Token revoked.'),
                                onError: (error) =>
                                    toast.error(getErrorMessage(error)),
                            });
                        }
                    }}
                >
                    <Trash2 aria-hidden />
                </Button>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {token.abilities.map((ability) => (
                    <Badge key={ability} variant="secondary">
                        <code className="text-[11px]">{ability}</code>
                    </Badge>
                ))}
            </div>
            <p className="text-xs text-muted-foreground">
                Created {formatDate(token.created_at)} · Last used{' '}
                {token.last_used_at
                    ? formatDateTime(token.last_used_at)
                    : 'never'}{' '}
                ·{' '}
                {token.expires_at
                    ? `${token.expired ? 'Expired' : 'Expires'} ${formatDate(token.expires_at)}`
                    : 'Never expires'}
            </p>
        </li>
    );
}
