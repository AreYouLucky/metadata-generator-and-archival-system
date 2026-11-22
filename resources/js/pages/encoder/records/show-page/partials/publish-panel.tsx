import { Link } from '@inertiajs/react';
import { ShieldAlert, X, Youtube } from 'lucide-react';
import EditorPanel from '@/components/encoder/editor-panel';
import { Button } from '@/components/ui/button';
import { index as youtubeSettings } from '@/routes/youtube';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Publication, Publishing } from '@/types/publishing';
import PublicationRow from './publication-row';
import PublishForm from './publish-form';

type Props = {
    recordId: string;
    publishing: Publishing | null;
    publications: Publication[];
};

/**
 * Opened from the header's Publish button. Publishing needs a connected
 * channel and a record that may leave the archive (approved, unclassified,
 * public rights, no embargo; the server checks again on every upload step).
 * Earlier publications are listed with their live status.
 */
export default function PublishPanel({
    recordId,
    publishing,
    publications,
}: Props) {
    const setInspectorTab = useWorkspaceStore((state) => state.setInspectorTab);
    const busy = publications.some((publication) =>
        ['queued', 'uploading', 'processing'].includes(publication.status),
    );
    const onYouTube = publications.some(
        (publication) =>
            publication.status === 'published' ||
            publication.status === 'processing',
    );

    return (
        <EditorPanel
            title="Publish to YouTube"
            icon={<Youtube className="size-3.5" aria-hidden />}
            actions={
                <Button
                    size="icon"
                    variant="ghost"
                    className="size-7"
                    onClick={() =>
                        setInspectorTab(
                            useWorkspaceStore.getState().previousTab,
                        )
                    }
                    aria-label="Close publish"
                >
                    <X aria-hidden />
                </Button>
            }
        >
            <div className="@container flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-5">
                {publishing === null ? null : !publishing.configured ? (
                    <p className="text-sm text-muted-foreground">
                        YouTube publishing is not set up on this server. Ask an
                        administrator to add a Google OAuth client.
                    </p>
                ) : publishing.blockers.length > 0 ? (
                    <div className="grid gap-1.5 rounded-lg border border-tertiary bg-tertiary/15 p-3 text-sm">
                        <p className="flex items-center gap-1.5 font-medium">
                            <ShieldAlert className="size-4" aria-hidden />
                            This record may not leave the archive yet
                        </p>
                        <ul className="list-disc pl-5 text-muted-foreground">
                            {publishing.blockers.map((blocker) => (
                                <li key={blocker}>{blocker}</li>
                            ))}
                        </ul>
                    </div>
                ) : !publishing.channel ? (
                    <div className="grid gap-2 text-sm">
                        <p className="text-muted-foreground">
                            Connect your YouTube channel first.
                        </p>
                        <Button
                            asChild
                            size="sm"
                            className="justify-self-start"
                        >
                            <Link href={youtubeSettings()}>
                                <Youtube aria-hidden />
                                Settings → YouTube
                            </Link>
                        </Button>
                    </div>
                ) : !publishing.has_video ? (
                    <p className="text-sm text-muted-foreground">
                        The record has no video file YouTube can take.
                    </p>
                ) : (
                    <>
                        <p className="text-xs text-muted-foreground">
                            To{' '}
                            <a
                                href={publishing.channel.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-foreground hover:underline"
                            >
                                {publishing.channel.title}
                            </a>
                            . Private by default; you can change it on YouTube
                            later.
                        </p>
                        {publishing.can_publish && (
                            <PublishForm
                                recordId={recordId}
                                publishing={publishing}
                                alreadyOnYouTube={onYouTube}
                                busy={busy}
                            />
                        )}
                    </>
                )}

                {publications.length > 0 && (
                    <section
                        aria-labelledby="youtube-history"
                        className="grid gap-2 border-t border-border pt-4"
                    >
                        <h3
                            id="youtube-history"
                            className="text-sm font-medium"
                        >
                            Publications
                        </h3>
                        <ul className="grid gap-2">
                            {publications.map((publication) => (
                                <PublicationRow
                                    key={publication.id}
                                    recordId={recordId}
                                    publication={publication}
                                />
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </EditorPanel>
    );
}
