import { FolderOpen, Import } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { BinItem } from '@/hooks/use-import-media';
import { formatBytes } from '@/lib/utilities';
import { cn } from '@/lib/utils';
import BinItemCard from './bin-item-card';
import UrlImportForm from './url-import-form';

const ACCEPT = 'video/*,.mxf,.mts,.m2ts';

type Props = {
    items: BinItem[];
    selectedId: string | null;
    /** Ids of items over the size or duration limit. */
    invalidIds: Set<string>;
    onAddFiles: (files: File[]) => void;
    onAddUrl: (url: string) => void;
    onSelect: (id: string) => void;
    onRemove: (id: string) => void;
    onMeasured: (id: string, duration: number | null) => void;
    disabled: boolean;
    maxUploadMb: number;
    maxVideoMinutes: number;
    maxBatchSize: number;
};

/**
 * The media bin: drop videos, paste them (Ctrl+V anywhere on the page),
 * browse for them, or add web addresses. One clip makes one record; several
 * make a batch. The server re-checks each file's real type after upload.
 */
export default function MediaBin({
    items,
    selectedId,
    invalidIds,
    onAddFiles,
    onAddUrl,
    onSelect,
    onRemove,
    onMeasured,
    disabled,
    maxUploadMb,
    maxVideoMinutes,
    maxBatchSize,
}: Props) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const full = items.length >= maxBatchSize;
    const locked = disabled || full;

    useEffect(() => {
        const onPaste = (event: ClipboardEvent) => {
            const pasted = Array.from(event.clipboardData?.files ?? []).filter(
                isVideo,
            );

            if (pasted.length > 0 && !locked) {
                event.preventDefault();
                onAddFiles(pasted);
            }
        };

        window.addEventListener('paste', onPaste);

        return () => window.removeEventListener('paste', onPaste);
    }, [locked, onAddFiles]);

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);

        const dropped = Array.from(event.dataTransfer.files).filter(isVideo);

        if (dropped.length > 0 && !locked) {
            onAddFiles(dropped);
        }
    };

    return (
        <EditorPanel
            title={`Media bin${items.length > 0 ? ` · ${items.length}` : ''}`}
            icon={<FolderOpen className="size-3.5" aria-hidden />}
            actions={
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs"
                    disabled={locked}
                    onClick={() => input.current?.click()}
                >
                    <Import aria-hidden />
                    Import
                </Button>
            }
        >
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
                className={cn(
                    'flex flex-1 flex-col gap-3 overflow-y-auto p-3 transition-colors',
                    dragging && 'bg-brand/10 ring-2 ring-brand ring-inset',
                )}
            >
                {items.length > 0 ? (
                    <ul className="grid gap-1.5" aria-label="Clips to import">
                        {items.map((item) => (
                            <BinItemCard
                                key={item.id}
                                item={item}
                                selected={item.id === selectedId}
                                invalid={invalidIds.has(item.id)}
                                disabled={disabled}
                                onSelect={() => onSelect(item.id)}
                                onRemove={() => onRemove(item.id)}
                                onMeasured={(duration) =>
                                    onMeasured(item.id, duration)
                                }
                            />
                        ))}
                    </ul>
                ) : (
                    <button
                        type="button"
                        disabled={locked}
                        onClick={() => input.current?.click()}
                        className="flex min-h-40 flex-1 flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-border p-6 text-center text-muted-foreground hover:border-brand/60 hover:text-foreground focus-visible:border-brand focus-visible:outline-none"
                    >
                        <Import className="size-8" aria-hidden />
                        <span className="text-sm font-medium">
                            Drop videos here
                        </span>
                        <span className="text-xs">
                            or paste them, or click to browse
                        </span>
                    </button>
                )}

                {invalidIds.size > 0 && (
                    <InputError
                        message={`Red clips are over the ${maxUploadMb} MB or ${maxVideoMinutes}-minute limit. Remove them to continue.`}
                    />
                )}
                {full && (
                    <p className="text-xs text-amber-700">
                        A batch holds at most {maxBatchSize} videos.
                    </p>
                )}

                <UrlImportForm disabled={locked} onAdd={onAddUrl} />

                <p className="mt-auto text-[11px] text-muted-foreground">
                    MP4, MOV, MXF, MKV, WebM and other common formats. Up to{' '}
                    {formatBytes(maxUploadMb * 1024 * 1024)} and{' '}
                    {maxVideoMinutes} minutes each. One clip makes a record;
                    several make a batch of up to {maxBatchSize}.
                </p>
            </div>
            <input
                ref={input}
                type="file"
                accept={ACCEPT}
                multiple
                className="sr-only"
                tabIndex={-1}
                aria-label="Video files"
                onChange={(event) => {
                    const chosen = Array.from(event.target.files ?? []);

                    if (chosen.length > 0) {
                        onAddFiles(chosen);
                    }

                    event.target.value = '';
                }}
            />
        </EditorPanel>
    );
}

function isVideo(file: File): boolean {
    return (
        file.type.startsWith('video/') || /\.(mxf|mts|m2ts)$/i.test(file.name)
    );
}
