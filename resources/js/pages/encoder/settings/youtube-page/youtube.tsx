import { Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import PublicationStatusBadge from '@/components/encoder/publication-status-badge';
import Heading from '@/components/heading';
import { formatDateTime } from '@/lib/utilities';
import { show } from '@/routes/records';
import { index } from '@/routes/youtube';
import type { PublicationWithRecord, YouTubeChannel } from '@/types/publishing';
import ChannelCard from './partials/channel-card';

type Props = {
    configured: boolean;
    channel: YouTubeChannel | null;
    redirectUri: string | null;
    publications: PublicationWithRecord[];
};

/**
 * Settings → YouTube: your connected channel and what you published
 * (publish from a record's workspace, Publish button).
 */
export default function YouTubeSettings({
    configured,
    channel,
    redirectUri,
    publications,
}: Props) {
    return (
        <>
            <Head title="YouTube" />

            <h1 className="sr-only">YouTube</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="YouTube"
                    description="Publish approved, public records to your channel from their workspace."
                />

                <ChannelCard
                    configured={configured}
                    channel={channel}
                    redirectUri={redirectUri}
                />

                <section className="grid gap-3">
                    <h3 className="text-sm font-medium">Your publications</h3>
                    {publications.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nothing published yet.
                        </p>
                    ) : (
                        <ul className="grid gap-2">
                            {publications.map((publication) => (
                                <li
                                    key={publication.id}
                                    className="grid gap-1 rounded-lg border p-3"
                                >
                                    <div className="flex items-center gap-2">
                                        <p className="min-w-0 flex-1 truncate text-sm font-medium">
                                            {publication.title}
                                        </p>
                                        <PublicationStatusBadge
                                            publication={publication}
                                        />
                                    </div>
                                    <p className="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                                        <span>
                                            {formatDateTime(
                                                publication.created_at,
                                            )}
                                        </span>
                                        <span>
                                            {publication.privacy_status} on{' '}
                                            {publication.channel_title}
                                        </span>
                                        {publication.record && (
                                            <Link
                                                href={show(
                                                    publication.record.id,
                                                )}
                                                className="hover:text-foreground hover:underline"
                                            >
                                                Record
                                            </Link>
                                        )}
                                        {publication.video_url && (
                                            <a
                                                href={publication.video_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-0.5 hover:text-foreground hover:underline"
                                            >
                                                Watch
                                                <ExternalLink
                                                    className="size-3"
                                                    aria-hidden
                                                />
                                            </a>
                                        )}
                                    </p>
                                    {publication.error && (
                                        <p className="text-xs text-destructive">
                                            {publication.error}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

YouTubeSettings.layout = {
    breadcrumbs: [{ title: 'YouTube', href: index() }],
};
