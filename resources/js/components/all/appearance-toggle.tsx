import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

/**
 * Switches between light and dark mode. The icon follows the `dark` class on
 * <html> through CSS, so server and client render the same markup.
 */
export default function AppearanceToggle({ className }: Props) {
    const { updateAppearance } = useAppearance();

    const toggleAppearance = (): void => {
        const isDark = document.documentElement.classList.contains('dark');

        updateAppearance(isDark ? 'light' : 'dark');
    };

    return (
        <button
            type="button"
            onClick={toggleAppearance}
            aria-label="Switch between light and dark mode"
            title="Switch between light and dark mode"
            className={cn(
                'relative flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-background/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                className,
            )}
        >
            <Sun className="absolute size-4 scale-0 -rotate-90 transition-all duration-500 dark:scale-100 dark:rotate-0" />
            <Moon className="absolute size-4 scale-100 rotate-0 transition-all duration-500 dark:scale-0 dark:rotate-90" />
        </button>
    );
}
