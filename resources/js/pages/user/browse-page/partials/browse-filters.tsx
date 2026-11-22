import type {
    CatalogueFacets,
    CatalogueFilters,
    CatalogueSeriesOption,
    CatalogueSort,
} from '@/types/catalogue';
import FilterSelect from './filter-select';

type Props = {
    filters: CatalogueFilters;
    facets: CatalogueFacets;
    seriesOptions: CatalogueSeriesOption[];
    onChange: (next: Partial<CatalogueFilters>) => void;
};

/** Newest first is the default (the select's empty choice). */
const SORTS: { value: CatalogueSort; label: string }[] = [
    { value: 'date_asc', label: 'Oldest first' },
    { value: 'title', label: 'Title A–Z' },
];

/**
 * The browse page's filters: event, series (once an event is chosen), genre,
 * video type, agency, region and sort order.
 */
export default function BrowseFilters({
    filters,
    facets,
    seriesOptions,
    onChange,
}: Props) {
    return (
        <div className="grid gap-4">
            <FilterSelect
                id="filter-project"
                label="Event or programme"
                value={filters.project}
                options={facets.projects.map((project) => ({
                    value: project.id,
                    label: project.title,
                    count: project.count,
                }))}
                onChange={(project) => onChange({ project, series: null })}
            />
            {filters.project && seriesOptions.length > 0 && (
                <FilterSelect
                    id="filter-series"
                    label="Series"
                    value={filters.series}
                    options={seriesOptions.map((series) => ({
                        value: series.id,
                        label: series.title,
                        count: series.count,
                    }))}
                    onChange={(series) => onChange({ series })}
                />
            )}
            <FilterSelect
                id="filter-genre"
                label="Genre"
                value={filters.genre}
                options={facets.genres.map((genre) => ({
                    value: genre.slug,
                    label: genre.name,
                    count: genre.count,
                }))}
                onChange={(genre) => onChange({ genre })}
            />
            <FilterSelect
                id="filter-type"
                label="Video type"
                value={filters.type}
                options={facets.types.map((type) => ({
                    value: type.slug,
                    label: type.name,
                    count: type.count,
                }))}
                onChange={(type) => onChange({ type })}
            />
            <FilterSelect
                id="filter-agency"
                label="Agency"
                value={filters.agency === null ? null : String(filters.agency)}
                options={facets.agencies.map((agency) => ({
                    value: String(agency.id),
                    label: agency.acronym
                        ? `${agency.name} (${agency.acronym})`
                        : agency.name,
                    count: agency.count,
                }))}
                onChange={(agency) =>
                    onChange({
                        agency: agency === null ? null : Number(agency),
                    })
                }
            />
            <FilterSelect
                id="filter-region"
                label="Region"
                value={filters.region === null ? null : String(filters.region)}
                options={facets.regions.map((region) => ({
                    value: String(region.id),
                    label: region.description
                        ? `${region.name} — ${region.description}`
                        : region.name,
                    count: region.count,
                }))}
                onChange={(region) =>
                    onChange({
                        region: region === null ? null : Number(region),
                    })
                }
            />
            <FilterSelect
                id="filter-sort"
                label="Sort"
                value={filters.sort === 'date_desc' ? null : filters.sort}
                anyLabel="Newest first"
                options={SORTS}
                onChange={(sort) =>
                    onChange({ sort: (sort ?? 'date_desc') as CatalogueSort })
                }
            />
        </div>
    );
}
