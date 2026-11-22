import { ExternalLink, Youtube } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useDisconnectYouTube } from '@/hooks/use-youtube';
import { formatDate, getErrorMessage } from '@/lib/utilities';
import { connect } from '@/routes/youtube';
import type { YouTubeChannel } from '@/types/publishing';

type Props = {
    configured: boolean;
    channel: YouTubeChannel | null;
    /** Admins only: the address to register on the Google OAuth client. */
    redirectUri: string | null;
};

/**
 * The connected channel, or a Connect button. Connecting is a full page
 * visit to Google's consent screen and back.
 */
export default function ChannelCard({
    configured,
    channel,
    redirectUri,
}: Props) {
    const disconnect = useDisconnectYouTube();

    if (!configured) {
        return (
            <div className="grid gap-2 rounded-lg border border-dashed p-4 text-sm">
                <p className="font-medium">
                    YouTube is not set up on this server.
                </p>
                <p className="text-muted-foreground">
                    An administrator adds a Google OAuth client (YouTube Data
                    API v3) as YOUTUBE_CLIENT_ID and YOUTUBE_CLIENT_SECRET.
                </p>
                {redirectUri && (
                    <p className="text-muted-foreground">
                        Authorized redirect URI:{' '}
                        <code className="text-xs break-all">{redirectUri}</code>
                    </p>
                )}
            </div>
        );
    }

    if (!channel) {
        return (
            <div className="flex flex-wrap items-center gap-3 rounded-lg border p-4">
                <Youtube className="size-5 text-muted-foreground" aria-hidden />
                <p className="min-w-0 flex-1 text-sm text-muted-foreground">
                    Connect the channel you publish to. You choose the privacy
                    of every video; nothing is posted without your say.
                </p>
                <Button asChild size="sm">
                    {/* A plain link: the server redirects to Google. */}
                    <a href={connect.url()}>Connect YouTube</a>
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-3 rounded-lg border p-4">
            {channel.thumbnail_url ? (
                <img
                    src={channel.thumbnail_url}
                    alt=""
                    className="size-10 rounded-full"
                    referrerPolicy="no-referrer"
                />
            ) : (
                <Youtube className="size-6 text-muted-foreground" aria-hidden />
            )}
            <div className="grid min-w-0 flex-1 gap-0.5">
                <a
                    href={channel.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 truncate font-medium hover:underline"
                >
                    {channel.title}
                    <ExternalLink className="size-3" aria-hidden />
                </a>
                <p className="text-xs text-muted-foreground">
                    Connected {formatDate(channel.connected_at ?? null)}
                </p>
            </div>
            <Button asChild size="sm" variant="outline">
                <a href={connect.url()}>Switch channel</a>
            </Button>
            <Button
                size="sm"
                variant="ghost"
                className="text-destructive"
                disabled={disconnect.isPending}
                onClick={() => {
                    if (
                        window.confirm(
                            'Disconnect YouTube? Videos already published stay on YouTube.',
                        )
                    ) {
                        disconnect.mutate(undefined, {
                            onSuccess: () =>
                                toast.success('YouTube disconnected.'),
                            onError: (error) =>
                                toast.error(getErrorMessage(error)),
                        });
                    }
                }}
            >
                {disconnect.isPending && <Spinner />}
                Disconnect
            </Button>
        </div>
    );
}
