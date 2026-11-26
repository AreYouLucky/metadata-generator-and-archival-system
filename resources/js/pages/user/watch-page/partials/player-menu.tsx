import { Check } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props<T> = {
    label: string;
    icon: ReactNode;
    value: T;
    options: { value: T; label: string }[];
    onChange: (value: T) => void;
    onOpenChange?: (open: boolean) => void;
};

/**
 * A small pop-up choice list for the player bar (captions, speed). It
 * renders inside the player rather than in a portal, so it still shows when
 * the player is fullscreen. Closes on a choice, Escape or a click outside.
 */
export default function PlayerMenu<T extends string | number | null>({
    label,
    icon,
    value,
    options,
    onChange,
    onOpenChange,
}: Props<T>) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const menuId = useId();

    const toggle = (next: boolean) => {
        setOpen(next);
        onOpenChange?.(next);
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent) => {
            if (!root.current?.contains(event.target as Node)) {
                setOpen(false);
                onOpenChange?.(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);

        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open, onOpenChange]);

    return (
        <div
            ref={root}
            className="relative"
            onKeyDown={(event) => {
                if (event.key === 'Escape' && open) {
                    event.stopPropagation();
                    toggle(false);
                }
            }}
        >
            <button
                type="button"
                aria-label={label}
                title={label}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => toggle(!open)}
                className={cn(
                    'inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-full px-2 text-white transition hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:outline-none',
                    open && 'bg-white/15',
                )}
            >
                {icon}
            </button>

            {open && (
                <div
                    id={menuId}
                    role="menu"
                    aria-label={label}
                    className="absolute right-0 bottom-full mb-3 grid max-h-64 min-w-40 overflow-y-auto rounded-xl bg-black/85 p-1.5 text-sm text-white shadow-xl ring-1 ring-white/10 backdrop-blur"
                >
                    <p className="px-3 pt-1.5 pb-2 text-xs font-medium text-white/60">
                        {label}
                    </p>
                    {options.map((option) => (
                        <button
                            key={String(option.value)}
                            type="button"
                            role="menuitemradio"
                            aria-checked={option.value === value}
                            onClick={() => {
                                onChange(option.value);
                                toggle(false);
                            }}
                            className={cn(
                                'flex items-center gap-2 rounded-lg px-3 py-2 text-left transition hover:bg-white/10 focus-visible:bg-white/10 focus-visible:outline-none',
                                option.value === value &&
                                    'bg-sky-600 font-medium hover:bg-sky-600 focus-visible:bg-sky-600',
                            )}
                        >
                            <Check
                                className={cn(
                                    'size-4 shrink-0',
                                    option.value !== value && 'invisible',
                                )}
                            />
                            {option.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
