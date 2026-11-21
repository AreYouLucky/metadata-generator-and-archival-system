import { Maximize2, ZoomIn, ZoomOut } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    pixelsPerSecond: number;
    fitPixelsPerSecond: number;
    maxPixelsPerSecond: number;
    /** null means "fit the whole video". */
    onZoom: (pixelsPerSecond: number | null) => void;
};

/**
 * Zoom out / slider / zoom in / fit. The slider is logarithmic, so each step
 * feels the same from "whole video" to "a few seconds".
 */
export default function TimelineZoom({
    pixelsPerSecond,
    fitPixelsPerSecond,
    maxPixelsPerSecond,
    onZoom,
}: Props) {
    const low = Math.min(fitPixelsPerSecond, maxPixelsPerSecond);
    const span = Math.log(maxPixelsPerSecond / low) || 1;
    const position = Math.round((Math.log(pixelsPerSecond / low) / span) * 100);
    const toPixels = (value: number) =>
        low * Math.exp((Math.min(100, Math.max(0, value)) / 100) * span);

    return (
        <div className="flex items-center gap-1">
            <Button
                variant="ghost"
                size="icon"
                className="size-7"
                onClick={() => onZoom(toPixels(position - 15))}
                aria-label="Zoom out"
            >
                <ZoomOut aria-hidden />
            </Button>
            <input
                type="range"
                min={0}
                max={100}
                value={Math.min(100, Math.max(0, position))}
                onChange={(event) =>
                    onZoom(toPixels(Number(event.target.value)))
                }
                aria-label="Timeline zoom"
                className="w-28 accent-[var(--brand)]"
            />
            <Button
                variant="ghost"
                size="icon"
                className="size-7"
                onClick={() => onZoom(toPixels(position + 15))}
                aria-label="Zoom in"
            >
                <ZoomIn aria-hidden />
            </Button>
            <Button
                variant="ghost"
                size="sm"
                className="h-7 px-2 text-xs"
                onClick={() => onZoom(null)}
            >
                <Maximize2 aria-hidden />
                Fit
            </Button>
        </div>
    );
}
