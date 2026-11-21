import { useMutation } from '@tanstack/react-query';
import { useEffect } from 'react';
import type { Worker } from 'tesseract.js';

/** An area of the frame, in the video's own pixels. */
export type FrameRegion = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type FrameText = {
    /** Where the frame was taken, in seconds. */
    time: number;
    /** The area that was read, as a JPEG data URL, shown next to the text. */
    image: string;
    /** True when only a picked area was read, not the whole frame. */
    cropped: boolean;
    text: string;
    lines: string[];
};

type Tesseract = typeof import('tesseract.js');

// One OCR worker per page, created on first use: tesseract.js and its English
// model (~10 MB, cached by the browser) only load when someone reads a frame.
let workerPromise: Promise<{ worker: Worker; PSM: Tesseract['PSM'] }> | null =
    null;

function getWorker(): Promise<{ worker: Worker; PSM: Tesseract['PSM'] }> {
    workerPromise ??= import('tesseract.js').then(
        async ({ createWorker, PSM }) => ({
            worker: await createWorker('eng'),
            PSM,
        }),
    );

    return workerPromise;
}

/** The longest side a picked area is enlarged to, and the most it is enlarged. */
const CROP_TARGET = 1600;
const CROP_MAX_SCALE = 4;

/**
 * Draw the paused frame (or the picked area of it) onto a canvas for the OCR.
 * Small frames are drawn at twice the size; a picked area is enlarged up to
 * four times and given a margin of its own background colour, since the OCR
 * misreads small text and text that touches the edge of the image.
 */
function captureFrame(
    video: HTMLVideoElement,
    region: FrameRegion | null,
): HTMLCanvasElement {
    const source = region ?? {
        x: 0,
        y: 0,
        width: video.videoWidth,
        height: video.videoHeight,
    };
    const scale = region
        ? Math.min(
              CROP_MAX_SCALE,
              Math.max(1, CROP_TARGET / Math.max(source.width, source.height)),
          )
        : video.videoHeight > 0 && video.videoHeight < 1080
          ? 2
          : 1;
    const width = Math.round(source.width * scale);
    const height = Math.round(source.height * scale);
    const margin = region ? Math.round(Math.min(width, height) * 0.15) : 0;

    const canvas = document.createElement('canvas');
    canvas.width = width + margin * 2;
    canvas.height = height + margin * 2;

    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        throw new Error('This browser cannot capture video frames.');
    }

    context.imageSmoothingQuality = 'high';
    context.drawImage(
        video,
        source.x,
        source.y,
        source.width,
        source.height,
        margin,
        margin,
        width,
        height,
    );

    if (margin > 0) {
        const [red, green, blue] = context.getImageData(
            margin,
            margin,
            1,
            1,
        ).data;
        context.fillStyle = `rgb(${red} ${green} ${blue})`;
        context.fillRect(0, 0, canvas.width, margin);
        context.fillRect(0, canvas.height - margin, canvas.width, margin);
        context.fillRect(0, margin, margin, height);
        context.fillRect(canvas.width - margin, margin, margin, height);
    }

    return canvas;
}

/**
 * Read the text visible in the player's current frame, or in an area the
 * encoder picked on it, in the browser (tesseract.js), so nothing is sent to
 * a server. The worker is released when the workspace closes.
 */
export function useFrameText() {
    useEffect(
        () => () => {
            const pending = workerPromise;
            workerPromise = null;
            void pending?.then(({ worker }) => worker.terminate());
        },
        [],
    );

    return useMutation({
        mutationFn: async ({
            video,
            region,
        }: {
            video: HTMLVideoElement;
            region: FrameRegion | null;
        }): Promise<FrameText> => {
            if (video.videoWidth === 0) {
                throw new Error('The video frame is not loaded yet.');
            }

            const time = video.currentTime;
            const canvas = captureFrame(video, region);
            const { worker, PSM } = await getWorker();

            // A picked area is one block of text; the whole frame needs the
            // page-layout analysis to find the text first.
            await worker.setParameters({
                tessedit_pageseg_mode: region ? PSM.SINGLE_BLOCK : PSM.AUTO,
            });

            const { data } = await worker.recognize(canvas);
            const lines = data.text
                .split('\n')
                .map((line) => line.replace(/\s+/g, ' ').trim())
                .filter((line) => line.length > 1);

            return {
                time,
                image: canvas.toDataURL('image/jpeg', 0.85),
                cropped: region !== null,
                text: lines.join('\n'),
                lines,
            };
        },
    });
}
