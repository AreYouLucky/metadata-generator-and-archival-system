import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import ProjectCard from '@/components/user/project-card';
import RecordGrid from '@/components/user/record-grid';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/utilities';
import { browse } from '@/routes/catalogue';
import type {
    CatalogueCard,
    CatalogueFacets,
    CatalogueProject,
} from '@/types/catalogue';

type Props = {
    latest: CatalogueCard[];
    projects: CatalogueProject[];
    genres: CatalogueFacets['genres'];
    total: number;
};

/**
 * The catalogue home: latest published videos, current and recent events,
 * and genres to start browsing from.
 */
export default function Home({ latest, projects, genres, total }: Props) {
    return (
        <>
            <Head title="Catalogue" />
            <div className="grid gap-12">
                <section className="grid gap-3">
                    <h1 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                        The video archive
                    </h1>
                    <p className="max-w-2xl text-muted-foreground">
                        {total === 0
                            ? 'Published videos will appear here.'
                            : `${formatNumber(total)} published ${total === 1 ? 'video' : 'videos'}, filed by event, series, date and place.`}
                    </p>
                    {genres.length > 0 && (
                        <ul
                            className="flex flex-wrap gap-2 pt-2"
                            aria-label="Genres"
                        >
                            {genres.map((genre) => (
                                <li key={genre.slug}>
                                    <Link
                                        href={browse({
                                            query: { genre: genre.slug },
                                        })}
                                        className="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition hover:border-brand/50 hover:bg-brand-muted"
                                    >
                                        {genre.name}
                                        <span className="text-xs text-muted-foreground">
                                            {genre.count}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-xl font-semibold">Latest videos</h2>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={browse()}>
                                Browse all
                                <ArrowRight aria-hidden />
                            </Link>
                        </Button>
                    </div>
                    <RecordGrid
                        records={latest}
                        empty="Nothing has been published yet."
                    />
                </section>

                {projects.length > 0 && (
                    <section className="grid gap-4">
                        <h2 className="text-xl font-semibold">
                            Events and programmes
                        </h2>
                        <ul className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            {projects.map((project) => (
                                <li key={project.id}>
                                    <ProjectCard project={project} />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}
