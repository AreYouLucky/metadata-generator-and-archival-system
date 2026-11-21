import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SELECT_FILL_TRUNCATE } from '@/lib/utilities';
import type { Option } from '@/types/archive';

type Props = {
    id: string;
    value: string | null;
    options: Option[];
    onChange: (value: string | null) => void;
    /** Label of the empty choice; omit to make a choice required. */
    emptyLabel?: string;
    disabled?: boolean;
};

const EMPTY = '__none__';

/** A select over value/label options, optionally with an empty choice. */
export default function ArchiveSelect({
    id,
    value,
    options,
    onChange,
    emptyLabel,
    disabled,
}: Props) {
    return (
        <Select
            value={value ?? (emptyLabel ? EMPTY : undefined)}
            onValueChange={(next) => onChange(next === EMPTY ? null : next)}
            disabled={disabled}
        >
            <SelectTrigger id={id} className={SELECT_FILL_TRUNCATE}>
                <SelectValue placeholder={emptyLabel ?? 'Choose…'} />
            </SelectTrigger>
            <SelectContent>
                {emptyLabel && (
                    <SelectItem value={EMPTY}>{emptyLabel}</SelectItem>
                )}
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
