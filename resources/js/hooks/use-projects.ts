import {
    keepPreviousData,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { destroy, options, store, update } from '@/routes/projects';
import { store as storeSeries } from '@/routes/projects/series';
import type {
    ProjectDetail,
    ProjectFormValues,
    ProjectOption,
    SeriesOption,
} from '@/types/projects';

const OPTIONS_KEY = ['projects', 'options'] as const;

/** The shared fields as JSON (series have no thumbnail). */
const toSeriesPayload = (values: ProjectFormValues) => ({
    title: values.title,
    date: values.date || null,
    end_date: values.end_date || null,
    description: values.description || null,
    location: values.location || null,
    agency_id: values.agency_id,
    region_id: values.region_id,
    prov_id: values.prov_id,
    city_id: values.city_id,
    brgy_id: values.brgy_id,
});

/**
 * A project as multipart, since it may carry a thumbnail. Empty fields are
 * sent as "" (Laravel turns them into null, which clears them on edit).
 */
const toProjectFormData = (values: ProjectFormValues): FormData => {
    const data = new FormData();

    Object.entries(toSeriesPayload(values)).forEach(([key, value]) =>
        data.append(key, value === null ? '' : String(value)),
    );

    if (values.thumbnail) {
        data.append('thumbnail', values.thumbnail);
    } else if (values.remove_thumbnail) {
        data.append('remove_thumbnail', '1');
    }

    return data;
};

/**
 * Projects (with series) for the Import media picker. Pass an already
 * debounced search; previous results stay on screen while the next load.
 */
export function useProjectOptions(search: string) {
    return useQuery({
        queryKey: [...OPTIONS_KEY, search],
        queryFn: async () =>
            (
                await http.get<{ data: ProjectOption[] }>(
                    options.url({ query: search ? { q: search } : {} }),
                )
            ).data.data,
        staleTime: 60_000,
        placeholderData: keepPreviousData,
    });
}

/**
 * Create a project and refresh the picker. Pages reload their own props.
 */
export function useCreateProject() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (values: ProjectFormValues) =>
            (
                await http.post<ProjectOption>(
                    store.url(),
                    toProjectFormData(values),
                )
            ).data,
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: OPTIONS_KEY });
        },
    });
}

/**
 * Edit a project (POST with `_method=PATCH`, as PHP only parses multipart
 * bodies on POST). Pages reload their own props.
 */
export function useUpdateProject(projectId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (values: ProjectFormValues) => {
            const data = toProjectFormData(values);
            data.append('_method', 'PATCH');

            return (await http.post<ProjectDetail>(update.url(projectId), data))
                .data;
        },
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: OPTIONS_KEY });
        },
    });
}

/**
 * Soft-delete a project; it leaves the picker straight away.
 */
export function useDeleteProject(projectId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async () =>
            (await http.delete<{ deleted: boolean }>(destroy.url(projectId)))
                .data,
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: OPTIONS_KEY });
        },
    });
}

/**
 * Add a series to a project and refresh the picker. Pages reload their own props.
 */
export function useCreateSeries(projectId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (values: ProjectFormValues) =>
            (
                await http.post<SeriesOption>(
                    storeSeries.url(projectId),
                    toSeriesPayload(values),
                )
            ).data,
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: OPTIONS_KEY });
        },
    });
}
