import { Head } from '@inertiajs/react';
import LookupManager from '@/components/admin/lookup-manager';
import { index } from '@/routes/admin/genres';
import type { LookupItem } from '@/types/lookups';

export default function Genres({ items }: { items: LookupItem[] }) {
    return (
        <>
            <Head title="Genres" />
            <LookupManager
                kind="genres"
                items={items}
                title="Genres"
                description="Genres encoders give each record, and visitors filter the public catalogue by."
                noun="genre"
            />
        </>
    );
}

Genres.layout = {
    breadcrumbs: [{ title: 'Genres', href: index() }],
};
