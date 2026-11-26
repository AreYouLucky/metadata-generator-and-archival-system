import { Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Looping, muted sample clip (Pexels free licence) at `public/videos/`.
 * Swap the files to change it: H.264, 720p, no audio, a few MB at most, a
 * steady shot whose last second is crossfaded into the first. The clip is
 * portrait, so `object-position` keeps the couple in the 16:9 frame.
 */
const VIDEO_SRC = '/videos/wedding-couple.mp4';
const POSTER_SRC = '/videos/wedding-couple.jpg';

/** Where the clip sits in the (sample) full recording, in seconds. */
const CLIP_OFFSET = 42 * 60 + 10;

/** Captions keyed to seconds into the 11-second clip, so they follow the loop. */
const CAPTIONS = [
    {
        start: 0,
        text: 'I promise to love you in every season of our lives.',
    },
    {
        start: 4,
        text: 'Mahal kita, ngayon at magpakailanman.',
    },
    {
        start: 8,
        text: 'Ladies and gentlemen, the newlyweds!',
    },
];

const CHAPTER_MARKS = [22, 48, 71];

function formatTimecode(totalSeconds: number): string {
    const seconds = Math.floor(totalSeconds);

    return [
        Math.floor(seconds / 3600),
        Math.floor((seconds % 3600) / 60),
        seconds % 60,
    ]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

/**
 * The hero player: the looping clip with its own captions, timecode and
 * progress, all driven by the video's current time.
 */
export default function HeroVideo() {
    const videoRef = useRef<HTMLVideoElement>(null);
    const [hasFailed, setHasFailed] = useState(false);
    const [isPlaying, setIsPlaying] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);
    const [duration, setDuration] = useState(0);

    useEffect(() => {
        const video = videoRef.current;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            video?.pause();
        }

        // The server-rendered video can start (or fail) before React
        // attaches its listeners, so read the state it is already in.
        if (video?.error) {
            setHasFailed(true);
        } else if (video && !video.paused) {
            setIsPlaying(true);
        }
    }, []);

    const caption =
        CAPTIONS.findLast((line) => currentTime >= line.start) ?? CAPTIONS[0];
    const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

    return (
        <>
            {!hasFailed && (
                <video
                    ref={videoRef}
                    src={VIDEO_SRC}
                    poster={POSTER_SRC}
                    autoPlay
                    muted
                    loop
                    playsInline
                    preload="auto"
                    tabIndex={-1}
                    onPlaying={() => setIsPlaying(true)}
                    onLoadedMetadata={(event) =>
                        setDuration(event.currentTarget.duration)
                    }
                    onTimeUpdate={(event) => {
                        setCurrentTime(event.currentTarget.currentTime);
                        setIsPlaying(!event.currentTarget.paused);
                    }}
                    onPause={() => setIsPlaying(false)}
                    onError={() => setHasFailed(true)}
                    className="absolute inset-0 size-full object-cover object-[50%_56%]"
                />
            )}

            <div className="absolute inset-x-0 top-0 flex items-center justify-between p-3 font-mono text-[11px] text-white">
                <span className="flex items-center gap-1.5 rounded-md bg-black/55 px-2 py-1">
                    <span className="size-1.5 rounded-full bg-red-500" />
                    MASTER · 1080p
                </span>
                <span className="rounded-md bg-black/55 px-2 py-1 tabular-nums">
                    {formatTimecode(CLIP_OFFSET + currentTime)}
                </span>
            </div>

            {!isPlaying && (
                <div className="absolute inset-0 flex items-center justify-center">
                    <span className="flex size-12 items-center justify-center rounded-full bg-black/55 text-white">
                        <Play className="size-5 translate-x-0.5 fill-current" />
                    </span>
                </div>
            )}

            <div className="absolute inset-x-0 bottom-0 space-y-3 p-3">
                <p className="mx-auto w-fit max-w-[92%] rounded-md bg-black/70 px-2.5 py-1 text-center text-xs leading-relaxed text-white sm:text-[13px]">
                    {caption.text}
                </p>
                <div className="relative h-1 rounded-full bg-white/30">
                    <div
                        className="h-full rounded-full bg-tertiary"
                        style={{ width: `${progress}%` }}
                    />
                    {CHAPTER_MARKS.map((mark) => (
                        <span
                            key={mark}
                            className="absolute -top-0.5 h-2 w-0.5 rounded-full bg-white"
                            style={{ left: `${mark}%` }}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}
