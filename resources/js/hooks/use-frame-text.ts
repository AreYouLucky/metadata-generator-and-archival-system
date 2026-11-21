import { useMutation } from '@tanstack/react-query';
import { useEffect } from 'react';
import type { Worker } from 'tesseract.js';

export type FrameText = {
    /** Where the frame was taken, in seconds. */
    time: number;
    /** The frame as a JPEG data URL, shown next to the text. */
    image: string;
    text: string;
    lines: string[];
};

// One OCR worker per page, created on first use: tesseract.js and its English
// model (~10 MB, cached by the browser) only load when someone reads a frame.
let workerPromise: Promise<Worker> | null = null;

function getWorker(): Promise<Worker> {
    workerPromise ??= import('tesseract.js').then(({ createWorker }) =>
        createWorker('eng'),
    );

    return workerPromise;
}

/**
 * The paused frame at full resolution. Small frames are drawn at twice the
 * size, which helps the OCR read lower-thirds and captions.
 */
function captureFrame(video: HTMLVideoElement): HTMLCanvasElement {
    const scale = video.videoHeight > 0 && video.videoHeight < 1080 ? 2 : 1;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth * scale;
    canvas.height = video.videoHeight * scale;

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('This browser cannot capture video frames.');
    }

    context.imageSmoothingQuality = 'high';
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas;
}

/**
 * Read the text visible in the player's current frame, in the browser
 * (tesseract.js), so nothing is sent to a server. The worker is released
 * when the workspace closes.
 */
export function useFrameText() {
    useEffect(
        () => () => {
            const pending = workerPromise;
            workerPromise = null;
            void pending?.then((worker) => worker.terminate());
        },
        [],
    );

    return useMutation({
        mutationFn: async (video: HTMLVideoElement): Promise<FrameText> => {
            if (video.videoWidth === 0) {
                throw new Error('The video frame is not loaded yet.');
            }

            const time = video.currentTime;
            const canvas = captureFrame(video);
            const worker = await getWorker();
            const { data } = await worker.recognize(canvas);
            const lines = data.text
                .split('\n')
                .map((line) => line.replace(/\s+/g, ' ').trim())
                .filter((line) => line.length > 1);

            return {
                time,
                image: canvas.toDataURL('image/jpeg', 0.8),
                text: lines.join('\n'),
                lines,
            };
        },
    });
}
