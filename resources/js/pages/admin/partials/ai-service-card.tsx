import { useQueryClient } from '@tanstack/react-query';
import { BrainCircuit, RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    aiServiceHealthKey,
    useAiServiceHealth,
} from '@/hooks/use-ai-service-health';
import { getErrorMessage } from '@/lib/utilities';

export default function AiServiceCard() {
    const queryClient = useQueryClient();
    const { data, error, isPending, isFetching } = useAiServiceHealth();

    const rows: [string, string][] = data?.reachable
        ? [
              ['Status', data.status ?? 'unknown'],
              ['Version', data.version ?? 'unknown'],
              ['GPU', data.gpu ? 'available' : 'not available'],
              ['Response time', `${data.latency_ms ?? '?'} ms`],
          ]
        : [];

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle className="flex items-center gap-2">
                        <BrainCircuit className="size-4 text-brand" />
                        AI service
                    </CardTitle>
                    <CardDescription>
                        Signed request from Laravel to the Python service.
                    </CardDescription>
                </div>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Check again"
                    disabled={isFetching}
                    onClick={() =>
                        void queryClient.invalidateQueries({
                            queryKey: aiServiceHealthKey,
                        })
                    }
                >
                    <RefreshCw className={isFetching ? 'animate-spin' : ''} />
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                {isPending ? (
                    <div className="space-y-2">
                        <Skeleton className="h-5 w-24" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-2/3" />
                    </div>
                ) : (
                    <>
                        {data?.reachable ? (
                            <Badge className="bg-emerald-600 text-white">
                                Reachable
                            </Badge>
                        ) : (
                            <Badge variant="destructive">Unreachable</Badge>
                        )}

                        {rows.length > 0 && (
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                {rows.map(([label, value]) => (
                                    <div key={label} className="contents">
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-medium">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}

                        {(data?.error ?? error) && (
                            <p className="text-sm text-muted-foreground">
                                {data?.error ?? getErrorMessage(error)} Start it
                                with <code>docker compose up ai-api</code>.
                            </p>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
