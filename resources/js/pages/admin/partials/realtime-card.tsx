import { useConnectionStatus } from '@laravel/echo-react';
import { Radio, Send, Workflow } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useSendSystemPing } from '@/hooks/use-send-system-ping';
import { useSystemPings } from '@/hooks/use-system-pings';
import { cn } from '@/lib/utils';
import { formatClockTime, getErrorMessage } from '@/lib/utilities';
import type { PingTarget } from '@/types/system';

type Props = {
    className?: string;
};

const SOURCE_LABELS = {
    laravel: 'Laravel',
    'ai-worker': 'AI worker (signed callback)',
} as const;

export default function RealtimeCard({ className }: Props) {
    const status = useConnectionStatus();
    const { pings, trackSent } = useSystemPings();
    const sendPing = useSendSystemPing();

    const send = (target: PingTarget) => {
        const startedAt = performance.now();

        sendPing.mutate(target, {
            onSuccess: ({ ping_id }) => trackSent(ping_id, target, startedAt),
            onError: (error) => toast.error(getErrorMessage(error)),
        });
    };

    const pendingTarget = sendPing.isPending ? sendPing.variables : null;

    return (
        <Card className={className}>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle className="flex items-center gap-2">
                        <Radio className="size-4 text-brand" />
                        Live updates
                    </CardTitle>
                    <CardDescription>
                        Send a test ping and watch it come back over the
                        WebSocket (Reverb).
                    </CardDescription>
                </div>
                <Badge
                    variant="outline"
                    className={cn(
                        'capitalize',
                        status === 'connected' &&
                            'border-emerald-600/40 text-emerald-700 dark:text-emerald-400',
                    )}
                >
                    {status}
                </Badge>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="flex flex-wrap gap-2">
                    <Button
                        onClick={() => send('broadcast')}
                        disabled={sendPing.isPending}
                    >
                        {pendingTarget === 'broadcast' ? <Spinner /> : <Send />}
                        Broadcast from Laravel
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => send('ai_service')}
                        disabled={sendPing.isPending}
                    >
                        {pendingTarget === 'ai_service' ? (
                            <Spinner />
                        ) : (
                            <Workflow />
                        )}
                        Round trip through the AI service
                    </Button>
                </div>

                {pings.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No pings received yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {pings.map((ping) => (
                            <li
                                key={ping.ping_id}
                                className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="size-2 rounded-full bg-emerald-500" />
                                    <span className="font-medium">
                                        {SOURCE_LABELS[ping.source]}
                                    </span>
                                    <code className="text-xs text-muted-foreground">
                                        {ping.ping_id.slice(0, 8)}
                                    </code>
                                </div>
                                <div className="flex items-center gap-4 text-muted-foreground tabular-nums">
                                    {ping.roundTripMs !== null && (
                                        <span>{ping.roundTripMs} ms</span>
                                    )}
                                    <span>
                                        {formatClockTime(ping.received_at)}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
