import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

/**
 * The yellow hand-drawn stroke under a headline's key words. It draws itself
 * in once; place it inside a `relative inline-block` span around the words.
 */
export default function ScribbleUnderline({ className }: Props) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 200 12"
            preserveAspectRatio="none"
            className={cn(
                'absolute -bottom-1.5 left-0 h-3 w-full text-tertiary',
                className,
            )}
        >
            <path
                d="M3 9 C 55 2, 140 2, 197 6"
                fill="none"
                stroke="currentColor"
                strokeWidth="6"
                strokeLinecap="round"
                pathLength={1}
                strokeDasharray={1}
                className="animate-draw"
            />
        </svg>
    );
}
