import { Maximize, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { PointerEvent } from 'react';
import { Button } from '@/components/ui/button';
import type { FrameRegion } from '@/hooks/use-frame-text';

type Props = {
    video: HTMLVideoElement;
    /** The picked area in video pixels, or null for the whole frame. */
    onPick: (region: FrameRegion | null) => void;
    onCancel: () => void;
};

type Box = { left: number; top: number; width: number; height: number };
type Drag = { startX: number; startY: number; x: number; y: number };

/** Drags smaller than this (in screen pixels) are ignored as stray clicks. */
const MIN_SIZE = 8;

/**
 * Where the picture sits inside the <video> element (it is letterboxed with
 * object-contain), relative to the monitor that holds both.
 */
function pictureBox(video: HTMLVideoElement): Box {
    const parent = video.parentElement?.getBoundingClientRect();
    const rect = video.getBoundingClientRect();
    const ratio = video.videoWidth / video.videoHeight || 16 / 9;
    let width = rect.width;
    let height = width / ratio;

    if (height > rect.height) {
        height = rect.height;
        width = height * ratio;
    }

    return {
        left: rect.left - (parent?.left ?? 0) + (rect.width - width) / 2,
        top: rect.top - (parent?.top ?? 0) + (rect.height - height) / 2,
        width,
        height,
    };
}

function clamp(value: number, max: number): number {
    return Math.min(Math.max(value, 0), max);
}

/**
 * Drag a box over the paused picture to choose which text "Copy text" reads.
 * Escape (or playing the video) cancels.
 */
export default function FrameRegionPicker({ video, onPick, onCancel }: Props) {
    const [box, setBox] = useState<Box>(() => pictureBox(video));
    const [drag, setDrag] = useState<Drag | null>(null);

    useEffect(() => {
        const observer = new ResizeObserver(() => setBox(pictureBox(video)));
        observer.observe(video);

        return () => observer.disconnect();
    }, [video]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onCancel();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [onCancel]);

    const point = (event: PointerEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        return {
            x: clamp(event.clientX - rect.left, rect.width),
            y: clamp(event.clientY - rect.top, rect.height),
        };
    };

    const onPointerDown = (event: PointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        const { x, y } = point(event);
        setDrag({ startX: x, startY: y, x, y });
    };

    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        if (drag) {
            setDrag({ ...drag, ...point(event) });
        }
    };

    const onPointerUp = () => {
        if (!drag) {
            return;
        }

        setDrag(null);
        const left = Math.min(drag.startX, drag.x);
        const top = Math.min(drag.startY, drag.y);
        const width = Math.abs(drag.x - drag.startX);
        const height = Math.abs(drag.y - drag.startY);

        if (width < MIN_SIZE || height < MIN_SIZE) {
            return;
        }

        const scaleX = video.videoWidth / box.width;
        const scaleY = video.videoHeight / box.height;
        onPick({
            x: Math.round(left * scaleX),
            y: Math.round(top * scaleY),
            width: Math.max(1, Math.round(width * scaleX)),
            height: Math.max(1, Math.round(height * scaleY)),
        });
    };

    const selection = drag && {
        left: Math.min(drag.startX, drag.x),
        top: Math.min(drag.startY, drag.y),
        width: Math.abs(drag.x - drag.startX),
        height: Math.abs(drag.y - drag.startY),
    };

    return (
        <>
            <div
                role="application"
                aria-label="Drag over the text to read"
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={() => setDrag(null)}
                style={box}
                className={`absolute z-10 cursor-crosshair touch-none overflow-hidden select-none ${selection ? '' : 'bg-black/45'}`}
            >
                {selection && (
                    <div
                        style={selection}
                        className="pointer-events-none absolute rounded-sm shadow-[0_0_0_9999px_rgb(0_0_0/0.55)] ring-2 ring-primary"
                    />
                )}
            </div>

            <div className="absolute inset-x-3 top-3 z-20 flex flex-wrap items-center justify-center gap-2">
                <p className="rounded-md bg-black/75 px-3 py-1.5 text-sm text-white shadow-lg backdrop-blur">
                    Drag a box over the text you want to copy
                </p>
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={() => onPick(null)}
                    className="bg-black/70 text-white shadow-lg backdrop-blur hover:bg-black/80"
                >
                    <Maximize aria-hidden />
                    Whole frame
                </Button>
                <Button
                    size="sm"
                    variant="secondary"
                    onClick={onCancel}
                    className="bg-black/70 text-white shadow-lg backdrop-blur hover:bg-black/80"
                    title="Cancel (Esc)"
                >
                    <X aria-hidden />
                    Cancel
                </Button>
            </div>
        </>
    );
}
