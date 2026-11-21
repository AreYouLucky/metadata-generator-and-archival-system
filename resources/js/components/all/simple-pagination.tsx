import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/pagination';

export default function SimplePagination({
    page,
}: {
    page: Paginated<unknown>;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    const previous = page.links[0]?.url ?? null;
    const next = page.links[page.links.length - 1]?.url ?? null;

    return (
        <nav
            className="flex items-center justify-between gap-4 text-sm"
            aria-label="Pagination"
        >
            <p className="text-muted-foreground">
                {page.from}–{page.to} of {page.total}
            </p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!previous}
                    asChild={!!previous}
                >
                    {previous ? (
                        <Link href={previous} preserveScroll>
                            Previous
                        </Link>
                    ) : (
                        <span>Previous</span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!next}
                    asChild={!!next}
                >
                    {next ? (
                        <Link href={next} preserveScroll>
                            Next
                        </Link>
                    ) : (
                        <span>Next</span>
                    )}
                </Button>
            </div>
        </nav>
    );
}
