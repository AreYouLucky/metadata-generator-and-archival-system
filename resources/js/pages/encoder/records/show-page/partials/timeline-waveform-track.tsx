import { useEffect, useRef } from 'react';
import { useWaveform } from '@/hooks/use-waveform';
import type { TimelineViewport } from './timeline';

type Props = {
    recordId: string;
    available: boolean;
    pixelsPerSecond: number;
    viewport: TimelineViewport;
};

/**
 * The audio waveform, drawn on a canvas the size of the visible window (a
 * full-length canvas would exceed browser limits on long videos). Peaks come
 * from the server, so the browser never decodes the media.
 */
export default function TimelineWaveformTrack({
    recordId,
    available,
    pixelsPerSecond,
    viewport,
}: Props) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const { data, isPending, isError } = useWaveform(recordId, available);

    useEffect(() => {
        const element = canvas.current;
        const context = element?.getContext('2d');

        if (!element || !context || !data || viewport.width === 0) {
            return;
        }

        const ratio = window.devicePixelRatio || 1;
        const height = element.clientHeight;
        element.width = Math.round(viewport.width * ratio);
        element.height = Math.round(height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, viewport.width, height);
        context.fillStyle =
            getComputedStyle(element).getPropertyValue('--brand').trim() ||
            '#0284c7';

        const perSecond = data.peaks_per_second;
        const middle = height / 2;

        for (let x = 0; x < viewport.width; x++) {
            const from = Math.floor(
                ((viewport.left + x) / pixelsPerSecond) * perSecond,
            );
            const to = Math.max(
                from + 1,
                Math.ceil(
                    ((viewport.left + x + 1) / pixelsPerSecond) * perSecond,
                ),
            );

            if (from >= data.peaks.length) {
                break;
            }

            let peak = 0;

            for (
                let index = from;
                index < to && index < data.peaks.length;
                index++
            ) {
                peak = Math.max(peak, data.peaks[index]);
            }

            const bar = Math.max(1, (peak / 100) * (height - 6));
            context.fillRect(x, middle - bar / 2, 1, bar);
        }
    }, [data, pixelsPerSecond, viewport]);

    if (!available || isError) {
        return (
            <p className="flex h-full items-center px-3 text-[11px] text-muted-foreground">
                {isError
                    ? 'The waveform could not be loaded.'
                    : 'The waveform appears after the audio is extracted.'}
            </p>
        );
    }

    return (
        <div className="relative h-full">
            {isPending && (
                <div className="absolute inset-2 animate-pulse rounded bg-muted/60" />
            )}
            <canvas
                ref={canvas}
                className="absolute top-0 h-full opacity-80"
                style={{ left: viewport.left, width: viewport.width }}
            />
        </div>
    );
}
