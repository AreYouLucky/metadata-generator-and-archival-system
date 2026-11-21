import { ExternalLink, Layers } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    horizonUrl: string;
};

export default function HorizonCard({ horizonUrl }: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Layers className="size-4 text-brand" />
                    Queue (Horizon)
                </CardTitle>
                <CardDescription>
                    Background jobs, failures and throughput. Admins only.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    Live updates are sent through the queue, so if test pings
                    never arrive, check here that the <code>horizon</code>{' '}
                    container is running.
                </p>
                <Button variant="outline" asChild>
                    <a href={horizonUrl} target="_blank" rel="noreferrer">
                        Open Horizon
                        <ExternalLink />
                    </a>
                </Button>
            </CardContent>
        </Card>
    );
}
