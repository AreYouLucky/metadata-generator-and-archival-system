import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { formatTimestamp } from '@/lib/utilities';
import { cn } from '@/lib/utils';

type Props = {
    video: HTMLVideoElement | null;
    time: number;
    duration: number;
    buffered: number;
    playing: boolean;
    chapters: { start: number; title: string }[];
    previews: { time_sec: number; url: string }[];
    onSeek: (seconds: number) => void;
    onScrubbingChange: (scrubbing: boolean) => void;
};

type Hover = { x: number; width: number; seconds: number };

/** The last item starting at or before `seconds` (items sorted by start). */
function lastBefore<T>(items: T[], start: (item: T) => number, seconds: number) {
    let found: T | null = null;

    for (const item of items) {
        if (start(item) > seconds) {
            break;
        }

        found = item;
    }

    return found;
}

/**
 * The player's timeline: buffered and played progress, chapter breaks, and
 * a hover preview with the time, the chapter and the nearest key frame.
 * Drag to scrub; arrows, Page Up/Down, Home and End work when focused. The
 * played bar is moved every frame through a CSS variable, so playback
 * doesn't re-render React 60 times a second.
 */
export default function PlayerSeekBar({
    video,
    time,
    duration,
    buffered,
    playing,
    chapters,
    previews,
    onSeek,
    onScrubbingChange,
}: Props) {
    const bar = useRef<HTMLDivElement>(null);
    const [hover, setHover] = useState<Hover | null>(null);
    const [scrubbing, setScrubbing] = useState(false);

    useEffect(() => {
        const element = bar.current;

        if (!element || scrubbing) {
            return;
        }

        const paint = (seconds: number) =>
            element.style.setProperty(
                '--progress',
                String(duration > 0 ? Math.min(1, seconds / duration) : 0),
            );

        if (!playing || !video) {
            paint(time);

            return;
        }

        let frame = requestAnimationFrame(function tick() {
            paint(video.currentTime);
            frame = requestAnimationFrame(tick);
        });

        return () => cancelAnimationFrame(frame);
    }, [video, time, duration, playing, scrubbing]);

    const positionAt = (event: PointerEvent<HTMLDivElement>): Hover => {
        const rect = event.currentTarget.getBoundingClientRect();
        const x = Math.min(rect.width, Math.max(0, event.clientX - rect.left));

        return {
            x,
            width: rect.width,
            seconds: rect.width > 0 ? (x / rect.width) * duration : 0,
        };
    };

    const scrubTo = (position: Hover) => {
        bar.current?.style.setProperty(
            '--progress',
            String(position.width > 0 ? position.x / position.width : 0),
        );
        onSeek(position.seconds);
    };

    const endScrub = () => {
        setScrubbing(false);
        onScrubbingChange(false);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const steps: Record<string, number> = {
            ArrowLeft: -5,
            ArrowRight: 5,
            ArrowDown: -5,
            ArrowUp: 5,
            PageDown: -duration / 10,
            PageUp: duration / 10,
        };

        if (event.key in steps) {
            onSeek(time + steps[event.key]);
        } else if (event.key === 'Home') {
            onSeek(0);
        } else if (event.key === 'End') {
            onSeek(duration);
        } else {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    };

    const chapter = hover
        ? lastBefore(chapters, (item) => item.start, hover.seconds)
        : null;
    const preview = hover
        ? lastBefore(previews, (item) => item.time_sec, hover.seconds)
        : null;
    const previewWidth = preview ? 176 : 120;
    const previewLeft = hover
        ? Math.min(
              hover.width - previewWidth / 2,
              Math.max(previewWidth / 2, hover.x),
          )
        : 0;

    return (
        <div
            ref={bar}
            role="slider"
            tabIndex={0}
            aria-label="Seek"
            aria-valuemin={0}
            aria-valuemax={Math.round(duration)}
            aria-valuenow={Math.round(time)}
            aria-valuetext={`${formatTimestamp(time)} of ${formatTimestamp(duration)}`}
            onKeyDown={onKeyDown}
            onPointerDown={(event) => {
                if (event.button !== 0 || duration <= 0) {
                    return;
                }

                event.currentTarget.setPointerCapture(event.pointerId);
                setScrubbing(true);
                onScrubbingChange(true);
                scrubTo(positionAt(event));
            }}
            onPointerMove={(event) => {
                const position = positionAt(event);
                setHover(position);

                if (scrubbing) {
                    scrubTo(position);
                }
            }}
            onPointerUp={endScrub}
            onPointerCancel={endScrub}
            onPointerLeave={() => setHover(null)}
            className="group/seek relative flex h-5 cursor-pointer touch-none items-center rounded-sm focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:outline-none"
        >
            <div
                className={cn(
                    'relative h-1 w-full overflow-hidden rounded-full bg-white/25 transition-[height] group-hover/seek:h-1.5',
                    scrubbing && 'h-1.5',
                )}
            >
                <div
                    className="absolute inset-y-0 left-0 bg-white/35"
                    style={{
                        width: `${duration > 0 ? Math.min(100, (buffered / duration) * 100) : 0}%`,
                    }}
                />
                {hover && (
                    <div
                        className="absolute inset-y-0 left-0 bg-white/30"
                        style={{ width: hover.x }}
                    />
                )}
                <div className="absolute inset-y-0 left-0 w-[calc(var(--progress,0)*100%)] bg-sky-500" />
                {duration > 0 &&
                    chapters
                        .filter((item) => item.start > 0)
                        .map((item) => (
                            <span
                                key={item.start}
                                className="absolute inset-y-0 w-0.5 -translate-x-1/2 bg-black/70"
                                style={{
                                    left: `${(item.start / duration) * 100}%`,
                                }}
                            />
                        ))}
            </div>

            <span
                className={cn(
                    'pointer-events-none absolute top-1/2 left-[calc(var(--progress,0)*100%)] size-3.5 -translate-x-1/2 -translate-y-1/2 scale-0 rounded-full bg-sky-500 shadow ring-2 ring-white transition-transform group-hover/seek:scale-100 group-focus-visible/seek:scale-100',
                    scrubbing && 'scale-100',
                )}
            />

            {hover && duration > 0 && (
                <div
                    className="pointer-events-none absolute bottom-full mb-3 grid -translate-x-1/2 justify-items-center gap-1.5"
                    style={{ left: previewLeft, width: previewWidth }}
                >
                    {preview && (
                        <img
                            src={preview.url}
                            alt=""
                            className="aspect-video w-full rounded-md bg-black object-cover shadow-lg ring-1 ring-white/20"
                        />
                    )}
                    {chapter && (
                        <span className="max-w-full truncate text-xs font-medium text-white drop-shadow">
                            {chapter.title}
                        </span>
                    )}
                    <span className="rounded bg-black/80 px-1.5 py-0.5 font-mono text-xs text-white tabular-nums">
                        {formatTimestamp(hover.seconds)}
                    </span>
                </div>
            )}
        </div>
    );
}
