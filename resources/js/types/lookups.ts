/** A genre or video type in its admin-managed list. */
export type LookupItem = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    records_count: number;
};

export type LookupKind = 'genres' | 'video-types';
