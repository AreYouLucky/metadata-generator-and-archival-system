import { Film, FolderOpen, Import } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import EditorPanel from '@/components/encoder/editor-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import type { LocalVideo } from '@/hooks/use-local-video';
import { formatBytes, formatTimestamp } from '@/lib/utilities';
import { cn } from '@/lib/utils';

const ACCEPT = 'video/*,.mxf,.mts,.m2ts';

type Props = {
    file: File | null;
    video: LocalVideo;
    onFile: (file: File) => void;
    disabled: boolean;
    maxUploadMb: number;
    maxVideoMinutes: number;
    tooLarge: boolean;
    tooLong: boolean;
};

/**
 * The media bin: drop a video, paste it (Ctrl+V anywhere on the page) or
 * browse for it. The chosen clip shows as a thumbnail card. The server
 * re-checks the real file type after upload.
 */
export default function MediaBin({
    file,
    video,
    onFile,
    disabled,
    maxUploadMb,
    maxVideoMinutes,
    tooLarge,
    tooLong,
}: Props) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        const onPaste = (event: ClipboardEvent) => {
            const pasted = Array.from(event.clipboardData?.files ?? []).find(
                isVideo,
            );

            if (pasted && !disabled) {
                event.preventDefault();
                onFile(pasted);
            }
        };

        window.addEventListener('paste', onPaste);

        return () => window.removeEventListener('paste', onPaste);
    }, [disabled, onFile]);

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);

        const dropped = Array.from(event.dataTransfer.files).find(isVideo);

        if (dropped && !disabled) {
            onFile(dropped);
        }
    };

    return (
        <EditorPanel
            title="Media bin"
            icon={<FolderOpen className="size-3.5" aria-hidden />}
            actions={
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs"
                    disabled={disabled}
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
                {file ? (
                    <figure
                        className={cn(
                            'overflow-hidden rounded-md bg-neutral-800 ring-1',
                            tooLarge || tooLong
                                ? 'ring-red-500/60'
                                : 'ring-brand/60',
                        )}
                    >
                        <div className="relative flex aspect-video items-center justify-center bg-black">
                            {video.thumbnail ? (
                                <img
                                    src={video.thumbnail}
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <Film
                                    className="size-8 text-neutral-600"
                                    aria-hidden
                                />
                            )}
                            {video.duration !== null && (
                                <span className="absolute right-1.5 bottom-1.5 rounded bg-black/70 px-1 font-mono text-[10px] text-white">
                                    {formatTimestamp(video.duration)}
                                </span>
                            )}
                        </div>
                        <figcaption className="space-y-0.5 p-2">
                            <p className="truncate text-xs font-medium text-neutral-100">
                                {file.name}
                            </p>
                            <p className="text-[11px] text-neutral-400">
                                {formatBytes(file.size)}
                                {file.type && ` · ${file.type}`}
                            </p>
                        </figcaption>
                    </figure>
                ) : (
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => input.current?.click()}
                        className="flex flex-1 flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-white/15 p-6 text-center text-neutral-400 hover:border-brand/60 hover:text-neutral-200 focus-visible:border-brand focus-visible:outline-none"
                    >
                        <Import className="size-8" aria-hidden />
                        <span className="text-sm font-medium">
                            Drop a video here
                        </span>
                        <span className="text-xs">
                            or paste it, or click to browse
                        </span>
                    </button>
                )}

                {tooLarge && (
                    <InputError
                        message={`This file is larger than the ${maxUploadMb} MB limit.`}
                    />
                )}
                {tooLong && (
                    <InputError
                        message={`This video is longer than the ${maxVideoMinutes}-minute limit.`}
                    />
                )}
                <p className="mt-auto text-[11px] text-neutral-500">
                    MP4, MOV, MXF, MKV, WebM and other common formats. Up to{' '}
                    {formatBytes(maxUploadMb * 1024 * 1024)} and{' '}
                    {maxVideoMinutes} minutes. One clip per record.
                </p>
            </div>
            <input
                ref={input}
                type="file"
                accept={ACCEPT}
                className="sr-only"
                tabIndex={-1}
                aria-label="Video file"
                onChange={(event) => {
                    const chosen = event.target.files?.[0];

                    if (chosen) {
                        onFile(chosen);
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
