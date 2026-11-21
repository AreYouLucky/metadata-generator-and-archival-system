import { Captions, Download, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { useStartTranslation } from '@/hooks/use-translations';
import { getErrorMessage, languageName } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import { exportMethod } from '@/routes/records';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Translation } from '@/types/records';
import TranslationViewerDialog from './translation-viewer-dialog';

type Props = {
    recordId: string;
    translation: Translation;
    canEdit: boolean;
    onStarted: (translation: Translation) => void;
};

const DOWNLOADS = [
    { format: 'srt', label: 'Subtitles (SRT)' },
    { format: 'vtt', label: 'Subtitles (WebVTT)' },
    { format: 'txt', label: 'Text' },
    { format: 'csv', label: 'CSV' },
] as const;

/**
 * One translation: its progress while running, then captions on the player,
 * read, download, or translate again (after the original was edited).
 */
export default function TranslationRow({
    recordId,
    translation,
    canEdit,
    onStarted,
}: Props) {
    const captionLanguage = useWorkspaceStore((state) => state.captionLanguage);
    const setCaptionLanguage = useWorkspaceStore(
        (state) => state.setCaptionLanguage,
    );
    const again = useStartTranslation(recordId);
    const busy = ['queued', 'processing'].includes(translation.status);
    const done = translation.status === 'done';
    const showing = captionLanguage === translation.language;
    const name = languageName(translation.language);

    const status = {
        queued: 'Waiting to start…',
        processing: `Translating… ${translation.progress}%`,
        done:
            `${translation.line_count} lines` +
            (translation.edited_count > 0
                ? ` · ${translation.edited_count} corrected`
                : ''),
        failed: 'Failed',
    }[translation.status];

    return (
        <li className="grid gap-2 rounded-lg border border-white/10 p-3">
            <div className="flex items-center gap-2">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{name}</p>
                    <p className="text-xs text-neutral-500">{status}</p>
                </div>

                {busy && <Spinner />}

                {done && (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className={cn('size-7', showing && 'text-brand')}
                            aria-pressed={showing}
                            aria-label={`${showing ? 'Hide' : 'Show'} ${name} captions on the player`}
                            title="Captions on the player"
                            onClick={() =>
                                setCaptionLanguage(
                                    showing ? null : translation.language,
                                )
                            }
                        >
                            <Captions aria-hidden />
                        </Button>
                        <TranslationViewerDialog
                            recordId={recordId}
                            language={translation.language}
                            canEdit={canEdit}
                        />
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    aria-label={`Download the ${name} translation`}
                                    title="Download"
                                >
                                    <Download aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {DOWNLOADS.map((item) => (
                                    <DropdownMenuItem key={item.format} asChild>
                                        <a
                                            href={exportMethod.url(recordId, {
                                                query: {
                                                    format: item.format,
                                                    language:
                                                        translation.language,
                                                },
                                            })}
                                            download
                                        >
                                            {item.label}
                                        </a>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </>
                )}

                {canEdit && !busy && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        disabled={again.isPending}
                        aria-label={`Translate into ${name} again`}
                        title="Translate again (after editing the transcript)"
                        onClick={() =>
                            (translation.edited_count === 0 ||
                                window.confirm(
                                    `Translating again replaces the ${translation.edited_count} ${translation.edited_count === 1 ? 'line' : 'lines'} you corrected. Continue?`,
                                )) &&
                            again.mutate(translation.language, {
                                onSuccess: onStarted,
                                onError: (error) =>
                                    toast.error(
                                        getErrorMessage(
                                            error,
                                            'The translation could not start.',
                                        ),
                                    ),
                            })
                        }
                    >
                        <RefreshCw aria-hidden />
                    </Button>
                )}
            </div>

            {translation.status === 'processing' && (
                <div
                    className="h-1 overflow-hidden rounded-full bg-white/10"
                    role="progressbar"
                    aria-valuenow={translation.progress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`${name} translation progress`}
                >
                    <div
                        className="h-full bg-brand transition-[width]"
                        style={{ width: `${translation.progress}%` }}
                    />
                </div>
            )}

            {translation.status === 'failed' && translation.error && (
                <p className="text-xs text-red-300">{translation.error}</p>
            )}
        </li>
    );
}
