import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = {
    titles: string[];
    selected: string | null;
    maxLength: number | null;
    disabled: boolean;
    onChange: (titles: string[], selected: string | null) => void;
};

/**
 * Pick one of the generated titles (radio) and edit any of them in place.
 */
export default function TitleOptions({
    titles,
    selected,
    maxLength,
    disabled,
    onChange,
}: Props) {
    const selectedIndex = Math.max(
        0,
        titles.findIndex((title) => title === selected),
    );

    const edit = (index: number, value: string) => {
        const next = titles.map((title, position) =>
            position === index ? value : title,
        );
        onChange(next, next[selectedIndex] ?? null);
    };

    return (
        <fieldset className="grid gap-2" disabled={disabled}>
            <legend className="mb-2 text-sm font-medium">Title</legend>
            {titles.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    No titles were generated.
                </p>
            )}
            {titles.map((title, index) => {
                const tooLong = maxLength !== null && title.length > maxLength;

                return (
                    <div key={index} className="flex items-center gap-3">
                        <input
                            type="radio"
                            name="selected-title"
                            className="size-4 accent-[var(--brand)]"
                            checked={index === selectedIndex}
                            onChange={() => onChange(titles, title)}
                            aria-label={`Use title option ${index + 1}`}
                        />
                        <Input
                            value={title}
                            aria-label={`Title option ${index + 1}`}
                            aria-invalid={tooLong || undefined}
                            onChange={(event) =>
                                edit(index, event.target.value)
                            }
                        />
                        {maxLength !== null && (
                            <span
                                className={cn(
                                    'w-14 shrink-0 text-right text-xs tabular-nums',
                                    tooLong
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {title.length}/{maxLength}
                            </span>
                        )}
                    </div>
                );
            })}
        </fieldset>
    );
}
