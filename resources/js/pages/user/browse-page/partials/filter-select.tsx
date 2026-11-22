import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SELECT_FILL_TRUNCATE } from '@/lib/utilities';

const ANY = 'any';

type Props = {
    id: string;
    label: string;
    value: string | null;
    options: { value: string; label: string; count?: number }[];
    onChange: (value: string | null) => void;
    /** What "no filter" is called; "Any" by default. */
    anyLabel?: string;
};

/**
 * One catalogue filter ("Any" clears it), with record counts per option.
 */
export default function FilterSelect({
    id,
    label,
    value,
    options,
    onChange,
    anyLabel = 'Any',
}: Props) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Select
                value={value ?? ANY}
                onValueChange={(next) => onChange(next === ANY ? null : next)}
            >
                <SelectTrigger
                    id={id}
                    className={`w-full ${SELECT_FILL_TRUNCATE}`}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ANY}>{anyLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                            {option.count !== undefined && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    ({option.count})
                                </span>
                            )}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
