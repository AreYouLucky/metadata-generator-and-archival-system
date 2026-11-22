import { cn } from '@/lib/utils';

/** The KeepFrame artwork in public/brand (copied from the owner's logo pack). */
const FILES = {
    compact: 'keepframe-logo-compact',
    horizontal: 'keepframe-logo-horizontal',
    stacked: 'keepframe-logo-stacked',
    mark: 'keepframe-mark',
} as const;

/** White versions exist for these, for dark backgrounds. */
const HAS_WHITE = new Set<keyof typeof FILES>([
    'compact',
    'horizontal',
    'mark',
]);

type Props = {
    /**
     * compact: mark + name (headers); horizontal: with the "Video archive &
     * metadata" line (footers); stacked: mark above the name; mark: the
     * bookmark in a frame alone (tight spaces, collapsed sidebar).
     */
    variant?: keyof typeof FILES;
    /** `white` on dark backgrounds. */
    tone?: 'default' | 'white';
    className?: string;
    /** Empty when a visible name or link label already says "KeepFrame". */
    alt?: string;
};

/**
 * The KeepFrame logo. Sized by height from `className` (e.g. `h-7`); the
 * width follows the artwork.
 */
export default function BrandLogo({
    variant = 'compact',
    tone = 'default',
    className,
    alt = 'KeepFrame',
}: Props) {
    const white = tone === 'white' && HAS_WHITE.has(variant) ? '-white' : '';

    return (
        <img
            src={`/brand/${FILES[variant]}${white}.svg`}
            alt={alt}
            className={cn('h-8 w-auto shrink-0 select-none', className)}
            draggable={false}
        />
    );
}
