import { useEffect, useRef } from "react";
import type { CSSProperties } from "react";
import ScribbleUnderline from "@/components/all/scribble-underline";
import type { AuthShowcaseClip } from "@/types";

/**
 * Looping, muted sample clips in `public/videos/` (Pexels free licence):
 * H.264, 720p, no audio, a few MB at most. Use steady (tripod) shots whose
 * last second is crossfaded into the first, so the loop has no visible jump.
 * `position` (the short band) and `positionLg` (the tall panel) keep the
 * subject in view, above the headline, where the frame crops the clip.
 */
const CLIPS: Record<
    AuthShowcaseClip,
    { src: string; poster: string; position: string; positionLg: string }
> = {
    meeting: {
        src: "/videos/meeting-town-hall.mp4",
        poster: "/videos/meeting-town-hall.jpg",
        position: "50% 50%",
        positionLg: "50% 50%",
    },
    wedding: {
        src: "/videos/wedding-couple.mp4",
        poster: "/videos/wedding-couple.jpg",
        position: "50% 58%",
        positionLg: "50% 72%",
    },
    birthday: {
        src: "/videos/birthday-candles.mp4",
        poster: "/videos/birthday-candles.jpg",
        position: "50% 55%",
        positionLg: "50% 55%",
    },
};

type Props = {
    /** Which clip plays; each page picks one through its layout props. */
    clip?: AuthShowcaseClip;
};

/**
 * Decorative film frame beside the auth forms: a looping sample clip on a
 * strip of film, with the headline over it. A full-height panel on
 * large screens, a short band above the form elsewhere. The clip stays on its
 * poster under reduced motion.
 */
export default function AuthShowcase({ clip = "meeting" }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const { src, poster, position, positionLg } = CLIPS[clip];

    useEffect(() => {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            videoRef.current?.pause();
        }
    }, [clip]);

    return (
        <div
            aria-hidden
            className="relative flex flex-col px-4 py-8 sm:px-6 lg:px-10 lg:py-14"
        >
            {/* <FilmSprockets className="absolute inset-x-0 top-2 text-black lg:top-4" />
            <FilmSprockets className="absolute inset-x-0 bottom-2 text-black lg:bottom-4" /> */}

            <div className="relative h-44 overflow-hidden rounded-lg bg-ink sm:h-52 lg:h-auto lg:flex-1 lg:rounded-xl">
                <video
                    key={clip}
                    ref={videoRef}
                    src={src}
                    poster={poster}
                    autoPlay
                    muted
                    loop
                    playsInline
                    tabIndex={-1}
                    style={
                        {
                            "--clip-position": position,
                            "--clip-position-lg": positionLg,
                        } as CSSProperties
                    }
                    className="absolute inset-0 size-full object-cover object-(--clip-position) lg:object-(--clip-position-lg)"
                />
                <div className="absolute inset-0 bg-linear-to-t from-black/90 via-black/35 to-black/10" />

                <div className="absolute inset-x-0 top-0 flex items-center justify-between p-3 font-mono text-[10px] text-white lg:p-5 lg:text-[11px]">
                    <span className="flex items-center gap-1.5 rounded-md bg-black/55 px-2 py-1">
                        <span className="size-1.5 animate-pulse rounded-full bg-red-500" />
                        MASTER · 1080p
                    </span>
                    <span className="hidden tracking-[0.3em] text-tertiary sm:block">
                        ▸ 01A KEEPFRAME 400
                    </span>
                </div>

                <div className="absolute inset-x-0 bottom-0 p-4 sm:p-6 lg:p-12">
                    <h2 className="max-w-xl animate-in text-2xl leading-tight font-semibold tracking-tight text-balance text-white duration-700 fill-mode-both fade-in slide-in-from-bottom-4 sm:text-3xl lg:text-5xl lg:leading-[1.05]">
                        Every moment is meant to be{" "}
                        <span className="relative inline-block whitespace-nowrap">
                            remembered.
                            <ScribbleUnderline />
                        </span>
                    </h2>
                    <p className="mt-5 hidden max-w-md leading-relaxed text-white/70 lg:block">
                        Transcribed, read frame by frame and checked by a person
                        before anything is shared.
                    </p>
                </div>
            </div>
        </div>
    );
}
