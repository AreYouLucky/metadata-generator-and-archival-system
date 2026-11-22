import { Head } from '@inertiajs/react';
import LookupManager from '@/components/admin/lookup-manager';
import { index } from '@/routes/admin/video-types';
import type { LookupItem } from '@/types/lookups';

export default function VideoTypes({ items }: { items: LookupItem[] }) {
    return (
        <>
            <Head title="Video types" />
            <LookupManager
                kind="video-types"
                items={items}
                title="Video types"
                description="Kinds of video (interview, highlights, raw footage…) encoders give each record, and visitors filter the public catalogue by."
                noun="video type"
            />
        </>
    );
}

VideoTypes.layout = {
    breadcrumbs: [{ title: 'Video types', href: index() }],
};
