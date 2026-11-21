import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { show } from '@/routes/admin/system';
import AiServiceCard from './partials/ai-service-card';
import HorizonCard from './partials/horizon-card';
import RealtimeCard from './partials/realtime-card';

type Props = {
    horizonUrl: string;
};

export default function SystemStatus({ horizonUrl }: Props) {
    return (
        <>
            <Head title="System status" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="System status"
                    description="Check that the AI service, the queue and live updates are working."
                />
                <div className="grid gap-4 lg:grid-cols-2">
                    <AiServiceCard />
                    <HorizonCard horizonUrl={horizonUrl} />
                    <RealtimeCard className="lg:col-span-2" />
                </div>
            </div>
        </>
    );
}

SystemStatus.layout = {
    breadcrumbs: [{ title: 'System status', href: show() }],
};
