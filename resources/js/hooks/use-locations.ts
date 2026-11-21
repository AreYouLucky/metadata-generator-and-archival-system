import { useQuery } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { barangays, cities, provinces, regions } from '@/routes/locations';

export type LocationOption = { id: number; name: string };

/** A region → province → city → barangay selection (ids of the seeded tables). */
export type LocationValue = {
    region_id: number | null;
    prov_id: number | null;
    city_id: number | null;
    brgy_id: number | null;
};

export const EMPTY_LOCATION: LocationValue = {
    region_id: null,
    prov_id: null,
    city_id: null,
    brgy_id: null,
};

type Level = 'regions' | 'provinces' | 'cities' | 'barangays';

const fetchList = async (url: string) =>
    (await http.get<{ data: LocationOption[] }>(url)).data.data;

/**
 * Seeded Philippine locations never change in the app, so each list is
 * fetched once per session.
 */
function useLocationList(
    level: Level,
    parent: number | null,
    url: () => string,
) {
    return useQuery({
        queryKey: ['locations', level, parent],
        queryFn: () => fetchList(url()),
        enabled: level === 'regions' || parent !== null,
        staleTime: Infinity,
        gcTime: 60 * 60_000,
    });
}

export function useRegions() {
    return useLocationList('regions', null, () => regions.url());
}

export function useProvinces(regionId: number | null) {
    return useLocationList('provinces', regionId, () =>
        provinces.url({ query: { region: regionId } }),
    );
}

export function useCities(provinceId: number | null) {
    return useLocationList('cities', provinceId, () =>
        cities.url({ query: { province: provinceId } }),
    );
}

export function useBarangays(cityId: number | null) {
    return useLocationList('barangays', cityId, () =>
        barangays.url({ query: { city: cityId } }),
    );
}
