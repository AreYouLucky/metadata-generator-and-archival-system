import { X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    id: string;
    label: string;
    values: string[];
    max: number;
    prefix?: string;
    disabled: boolean;
    onChange: (values: string[]) => void;
};

/**
 * Editable chips: Enter or comma adds, Backspace on an empty field removes the last.
 */
export default function ChipInput({
    id,
    label,
    values,
    max,
    prefix = '',
    disabled,
    onChange,
}: Props) {
    const [text, setText] = useState('');

    const add = () => {
        const cleaned = text.trim().replace(/^#+/, '');
        const value = cleaned === '' ? '' : `${prefix}${cleaned}`;

        if (
            value !== '' &&
            values.length < max &&
            !values.some((item) => item.toLowerCase() === value.toLowerCase())
        ) {
            onChange([...values, value]);
        }

        setText('');
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-baseline justify-between">
                <Label htmlFor={id}>{label}</Label>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {values.length} / {max}
                </span>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {values.map((value) => (
                    <Badge
                        key={value}
                        variant="secondary"
                        className="gap-1 pr-1"
                    >
                        {value}
                        {!disabled && (
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(
                                        values.filter((item) => item !== value),
                                    )
                                }
                                className="rounded-sm p-0.5 hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                aria-label={`Remove ${value}`}
                            >
                                <X className="size-3" aria-hidden />
                            </button>
                        )}
                    </Badge>
                ))}
            </div>
            {!disabled && values.length < max && (
                <Input
                    id={id}
                    value={text}
                    placeholder={`Add ${label.toLowerCase()} and press Enter`}
                    onChange={(event) => setText(event.target.value)}
                    onBlur={add}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ',') {
                            event.preventDefault();
                            add();
                        }

                        if (
                            event.key === 'Backspace' &&
                            text === '' &&
                            values.length > 0
                        ) {
                            onChange(values.slice(0, -1));
                        }
                    }}
                />
            )}
        </div>
    );
}
