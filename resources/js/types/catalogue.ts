import type { Paginated } from '@/types/pagination';

/** A record card in the public catalogue grids. */
export type CatalogueCard = {
    id: string;
    title: string;
    /** Date recorded, else the project's first day, else the publish date. */
    date: string | null;
    project: string | null;
    series: string | null;
    genre: string | null;
    video_type: string | null;
    agency: string | null;
    duration: number | null;
    thumbnail: string | null;
};

export type CatalogueProject = {
    id: string;
    title: string;
    date: string | null;
    end_date: string | null;
    is_current: boolean;
    thumbnail: string | null;
    count?: number;
};

export type CatalogueSeriesOption = {
    id: string;
    title: string;
    date: string | null;
    end_date: string | null;
    count: number;
};

export type CatalogueFacets = {
    genres: { slug: string; name: string; count: number }[];
    types: { slug: string; name: string; count: number }[];
    agencies: {
        id: number;
        name: string;
        acronym: string | null;
        count: number;
    }[];
    regions: {
        id: number;
        name: string;
        description: string | null;
        count: number;
    }[];
    projects: { id: string; title: string; count: number }[];
    years: { year: number; count: number }[];
};

export type CatalogueSort = 'date_desc' | 'date_asc' | 'title';

export type CatalogueFilters = {
    project: string | null;
    series: string | null;
    genre: string | null;
    type: string | null;
    agency: number | null;
    region: number | null;
    year: number | null;
    month: number | null;
    q: string | null;
    sort: CatalogueSort;
};

export type CataloguePage = Paginated<CatalogueCard>;

/** The watch page's public fields only (PROJECT_SPEC §1). */
export type WatchRecord = {
    id: string;
    title: string;
    alternative_title: string | null;
    description: string | null;
    date_recorded: string | null;
    date_recorded_precision: string | null;
    published_at: string | null;
    duration: number | null;
    language: string | null;
    project: { id: string; title: string } | null;
    series: { id: string; title: string } | null;
    genre: { slug: string; name: string } | null;
    video_type: { slug: string; name: string } | null;
    agency: { id: number; name: string; acronym: string | null } | null;
    division: string | null;
    place: string | null;
    location: string | null;
    subjects: { subject: string; type: string }[];
    keywords: string[];
    credits: { role: string; name: string }[];
    chapters: { start: number; title: string }[];
    license: string | null;
    license_url: string | null;
    usage_restrictions: string | null;
    /** The record's thumbnail, else its project's cover (the player's poster). */
    thumbnail: string | null;
};

export type WatchTranscriptLine = {
    start: number;
    end: number;
    speaker: string | null;
    text: string;
};

export type WatchCaption = { language: string; is_original: boolean };

/** A key frame on the public page: what it shows (no face counts). */
export type WatchFrame = {
    id: number;
    time_sec: number;
    url: string;
    description: string | null;
    ocr_text: string | null;
    /** Object labels, most confident first. */
    objects: string[];
};
