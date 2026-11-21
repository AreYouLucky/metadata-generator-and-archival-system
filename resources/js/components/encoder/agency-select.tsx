import { Label } from '@/components/ui/label';
import { SELECT_FILL_TRUNCATE } from '@/lib/utilities';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAgencyOptions } from '@/hooks/use-agencies';

const NONE = 'none';

type Props = {
    agencyId: number | null;
    divisionId?: number | null;
    /** Also pick one of the agency's production units. */
    withUnit?: boolean;
    disabled?: boolean;
    idPrefix?: string;
    onChange: (agencyId: number | null, divisionId: number | null) => void;
};

/**
 * Producing agency and, optionally, its production unit. Changing the agency
 * clears the unit. Admins manage the list on the Agencies page.
 */
export default function AgencySelect({
    agencyId,
    divisionId = null,
    withUnit = false,
    disabled = false,
    idPrefix = 'agency',
    onChange,
}: Props) {
    const agencies = useAgencyOptions();
    const units =
        agencies.data?.find((agency) => agency.id === agencyId)?.units ?? [];

    return (
        // Laid out by the space it gets (a dialog, the upload inspector or the
        // workspace's narrow Archive tab), not by the window width.
        <div className="@container">
            <div className={withUnit ? 'grid gap-4 @md:grid-cols-2' : 'grid'}>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor={`${idPrefix}-agency`}>Agency</Label>
                    <Select
                        value={agencyId === null ? NONE : String(agencyId)}
                        disabled={disabled || agencies.isPending}
                        onValueChange={(value) =>
                            onChange(
                                value === NONE ? null : Number(value),
                                null,
                            )
                        }
                    >
                        <SelectTrigger
                            id={`${idPrefix}-agency`}
                            className={SELECT_FILL_TRUNCATE}
                        >
                            <SelectValue
                                placeholder={
                                    agencies.isPending ? 'Loading…' : 'Not set'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>Not set</SelectItem>
                            {agencies.data?.map((agency) => (
                                <SelectItem
                                    key={agency.id}
                                    value={String(agency.id)}
                                >
                                    {agency.acronym
                                        ? `${agency.acronym} — ${agency.name}`
                                        : agency.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                {withUnit && (
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor={`${idPrefix}-unit`}>
                            Production unit
                        </Label>
                        <Select
                            value={
                                divisionId === null ? NONE : String(divisionId)
                            }
                            disabled={
                                disabled ||
                                agencyId === null ||
                                units.length === 0
                            }
                            onValueChange={(value) =>
                                onChange(
                                    agencyId,
                                    value === NONE ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger
                                id={`${idPrefix}-unit`}
                                className={SELECT_FILL_TRUNCATE}
                            >
                                <SelectValue placeholder="Not set" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Not set</SelectItem>
                                {units.map((unit) => (
                                    <SelectItem
                                        key={unit.id}
                                        value={String(unit.id)}
                                    >
                                        {unit.acronym
                                            ? `${unit.acronym} — ${unit.name}`
                                            : unit.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </div>
        </div>
    );
}
