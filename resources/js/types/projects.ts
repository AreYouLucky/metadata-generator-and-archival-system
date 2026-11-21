import type { LocationValue } from '@/hooks/use-locations';
import type { AgencySummary } from '@/types/agencies';
import type { ProcessingStatus, ReviewStatus } from '@/types/records';

export type SeriesOption = {
    id: string;
    title: string;
    date: string | null;
    end_date: string | null;
};

/** A project as the Import media picker receives it (with its series). */
export type ProjectOption = {
    id: string;
    title: string;
    /** First day; `end_date` is the last day of a multi-day event. */
    date: string | null;
    end_date: string | null;
    location: string | null;
    /** "Barangay, City, Province" from the location pickers. */
    place: string | null;
    agency: AgencySummary | null;
    series: SeriesOption[];
};

/** Everything the project cards, page and edit form show. */
export type ProjectDetail = LocationValue & {
    id: string;
    title: string;
    date: string | null;
    end_date: string | null;
    description: string | null;
    location: string | null;
    place: string | null;
    agency: AgencySummary | null;
    thumbnail_url: string | null;
};

export type ProjectListItem = ProjectDetail & {
    series_count: number;
    records_count: number;
};

export type ProjectSeriesItem = LocationValue & {
    id: string;
    title: string;
    date: string | null;
    end_date: string | null;
    description: string | null;
    location: string | null;
    place: string | null;
    agency_id: number | null;
    records_count: number;
};

export type ProjectRecordItem = {
    id: string;
    title: string;
    series: string | null;
    processing_status: ProcessingStatus;
    review_status: ReviewStatus;
    created_at: string | null;
};

/** Fields of the project and series forms (the thumbnail is projects only). */
export type ProjectFormValues = LocationValue & {
    title: string;
    date: string;
    end_date: string;
    description: string;
    location: string;
    agency_id: number | null;
    thumbnail: File | null;
    remove_thumbnail: boolean;
};
