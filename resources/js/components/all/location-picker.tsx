import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    useBarangays,
    useCities,
    useProvinces,
    useRegions,
} from '@/hooks/use-locations';
import { SELECT_FILL_TRUNCATE } from '@/lib/utilities';
import { EMPTY_LOCATION } from '@/hooks/use-locations';
import type { LocationOption, LocationValue } from '@/hooks/use-locations';

const NONE = 'none';

type Props = {
    value: LocationValue;
    onChange: (value: LocationValue) => void;
    errors?: Partial<Record<keyof LocationValue, string>>;
    disabled?: boolean;
    idPrefix?: string;
};

/**
 * Cascading region → province → city/municipality → barangay selects backed
 * by the seeded Philippine location tables (PROJECT_SPEC §9). Changing a
 * level clears the levels below it.
 */
export default function LocationPicker({
    value,
    onChange,
    errors = {},
    disabled = false,
    idPrefix = 'location',
}: Props) {
    const regions = useRegions();
    const provinces = useProvinces(value.region_id);
    const cities = useCities(value.prov_id);
    const barangays = useBarangays(value.city_id);

    const levels: {
        key: keyof LocationValue;
        label: string;
        options: LocationOption[] | undefined;
        loading: boolean;
        enabled: boolean;
        onSelect: (id: number | null) => void;
    }[] = [
        {
            key: 'region_id',
            label: 'Region',
            options: regions.data,
            loading: regions.isPending,
            enabled: true,
            onSelect: (id) => onChange({ ...EMPTY_LOCATION, region_id: id }),
        },
        {
            key: 'prov_id',
            label: 'Province',
            options: provinces.data,
            loading: provinces.isFetching,
            enabled: value.region_id !== null,
            onSelect: (id) =>
                onChange({
                    ...value,
                    prov_id: id,
                    city_id: null,
                    brgy_id: null,
                }),
        },
        {
            key: 'city_id',
            label: 'City / municipality',
            options: cities.data,
            loading: cities.isFetching,
            enabled: value.prov_id !== null,
            onSelect: (id) =>
                onChange({ ...value, city_id: id, brgy_id: null }),
        },
        {
            key: 'brgy_id',
            label: 'Barangay',
            options: barangays.data,
            loading: barangays.isFetching,
            enabled: value.city_id !== null,
            onSelect: (id) => onChange({ ...value, brgy_id: id }),
        },
    ];

    return (
        <div className="@container">
            <div className="grid gap-4 @md:grid-cols-2">
                {levels.map((level) => (
                    <div key={level.key} className="grid min-w-0 gap-2">
                        <Label htmlFor={`${idPrefix}-${level.key}`}>
                            {level.label}
                        </Label>
                        <Select
                            value={
                                value[level.key] === null
                                    ? NONE
                                    : String(value[level.key])
                            }
                            disabled={
                                disabled || !level.enabled || level.loading
                            }
                            onValueChange={(selected) =>
                                level.onSelect(
                                    selected === NONE ? null : Number(selected),
                                )
                            }
                        >
                            <SelectTrigger
                                className={SELECT_FILL_TRUNCATE}
                                id={`${idPrefix}-${level.key}`}
                            >
                                <SelectValue
                                    placeholder={
                                        level.loading ? 'Loading…' : 'Not set'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent className="max-h-72">
                                <SelectItem value={NONE}>Not set</SelectItem>
                                {(level.options ?? []).map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors[level.key]} />
                    </div>
                ))}
            </div>
        </div>
    );
}
