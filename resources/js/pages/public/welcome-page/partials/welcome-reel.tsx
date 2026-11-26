import { Children, useEffect, useRef } from "react";
import type { CSSProperties, ReactNode } from "react";
import { useWelcomeReelStore } from "@/stores/welcome-reel-store";
import FilmSprockets from "@/components/all/film-sprockets";
import { WELCOME_SECTIONS } from "./welcome-sections";

type Props = {
    children: ReactNode;
};

/** Matches the `fold` variant in app.css: wide and tall enough for the reel. */
const REEL_QUERY = "(min-width: 64rem) and (min-height: 45rem)";

/**
 * The section id of each frame, in order. The hero (frame 0) has none; the
 * rest follow WELCOME_SECTIONS, which lists the folds in page order.
 */
const FRAME_IDS: (string | null)[] = [
    null,
    ...WELCOME_SECTIONS.map((section) => section.id),
];

/**
 * Lays the folds out as frames on a strip of film. On large, tall screens the
 * strip pins to the viewport and slides sideways as the page scrolls down,
 * one frame per screen height, like a reel advancing; elsewhere the folds
 * simply stack, and the progress and frame in view follow the page scroll.
 *
 * The transform is written straight to the track on each animation frame, so
 * scrolling never re-renders React. Each frame has an empty snap marker in
 * the tall outer box, and in-page links to a section scroll to its marker
 * (the section's own anchor sits inside the moving strip).
 */
export default function WelcomeReel({ children }: Props) {
    const frames = Children.toArray(children);
    const outerRef = useRef<HTMLDivElement>(null);
    const viewportRef = useRef<HTMLDivElement>(null);
    const trackRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const outer = outerRef.current;
        const viewport = viewportRef.current;
        const track = trackRef.current;

        if (!outer || !viewport || !track) {
            return;
        }

        const media = window.matchMedia(REEL_QUERY);
        const root = document.documentElement;
        const { setActiveFrame } = useWelcomeReelStore.getState();
        const frameElements = Array.from(
            track.querySelectorAll<HTMLElement>(":scope > [data-reel-frame]"),
        );
        const lastFrame = frames.length - 1;
        let pending = 0;
        let activeFrame = -1;

        const showFrame = (frame: number) => {
            if (frame !== activeFrame) {
                activeFrame = frame;
                setActiveFrame(frame);
            }
        };

        /**
         * In the stacked layout the frame in view is the last one whose top
         * has passed 40% of the screen height.
         */
        const stackedFrame = (): number => {
            const line = window.innerHeight * 0.4;
            let frame = 0;

            frameElements.forEach((element, index) => {
                if (element.getBoundingClientRect().top <= line) {
                    frame = index;
                }
            });

            return frame;
        };

        const update = () => {
            pending = 0;

            const range = outer.offsetHeight - window.innerHeight;
            const scrolled = -outer.getBoundingClientRect().top;
            const progress =
                range > 0 ? Math.min(1, Math.max(0, scrolled / range)) : 0;

            root.style.setProperty("--reel-progress", progress.toFixed(4));

            if (!media.matches) {
                track.style.transform = "";
                showFrame(stackedFrame());

                return;
            }

            const distance = track.scrollWidth - viewport.clientWidth;

            track.style.transform = `translate3d(${-progress * distance}px, 0, 0)`;
            showFrame(Math.round(progress * lastFrame));
        };

        const schedule = () => {
            if (pending === 0) {
                pending = window.requestAnimationFrame(update);
            }
        };

        const jumpToFrame = (event: MouseEvent) => {
            if (!media.matches || !(event.target instanceof Element)) {
                return;
            }

            const link = event.target.closest('a[href^="#"]');
            const index = FRAME_IDS.indexOf(
                link?.getAttribute("href")?.slice(1) ?? "",
            );

            if (index < 1) {
                return;
            }

            event.preventDefault();
            window.scrollTo({
                top:
                    outer.getBoundingClientRect().top +
                    window.scrollY +
                    index * window.innerHeight,
            });
        };

        update();
        window.addEventListener("scroll", schedule, { passive: true });
        window.addEventListener("resize", schedule);
        media.addEventListener("change", schedule);
        document.addEventListener("click", jumpToFrame);

        return () => {
            window.cancelAnimationFrame(pending);
            window.removeEventListener("scroll", schedule);
            window.removeEventListener("resize", schedule);
            media.removeEventListener("change", schedule);
            document.removeEventListener("click", jumpToFrame);
            root.style.removeProperty("--reel-progress");
            setActiveFrame(-1);
        };
    }, [frames.length]);

    return (
        <div
            ref={outerRef}
            className="relative fold:h-(--reel-height)"
            style={
                {
                    "--reel-height": `${frames.length * 100}svh`,
                } as CSSProperties
            }
        >
            {frames.map((_, index) => (
                <div
                    key={index}
                    aria-hidden
                    className="absolute inset-x-0 hidden h-px snap-start fold:block"
                    style={{ top: `${index * 100}svh` }}
                />
            ))}

            <div
                ref={viewportRef}
                className="fold:sticky fold:top-0 fold:-mx-10 fold:flex fold:h-svh fold:flex-col fold:overflow-hidden fold:pt-17"
            >
                <div
                    ref={trackRef}
                    className="flex flex-col gap-4 will-change-transform lg:gap-6 fold:relative fold:h-full fold:w-max fold:flex-row fold:gap-10 fold:bg-foreground fold:px-10 fold:py-12"
                >
                    <FilmSprockets className="absolute inset-x-0 top-2.5 hidden text-white fold:block" />
                    <FilmSprockets className="absolute inset-x-0 bottom-2.5 hidden text-white fold:block" />

                    {frames.map((frame, index) => (
                        <div
                            key={index}
                            data-reel-frame
                            className="relative fold:h-full fold:w-[calc(100vw-10rem)] fold:shrink-0"
                        >
                            <span
                                aria-hidden
                                className="absolute -top-4 left-3 hidden font-mono text-[10px] leading-none tracking-[0.3em] text-tertiary fold:block"
                            >
                                ▸ {String(index + 1).padStart(2, "0")}A
                                KEEPFRAME 400
                            </span>
                            {frame}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
