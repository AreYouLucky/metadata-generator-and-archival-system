import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import ProjectCard from '@/components/user/project-card';
import RecordGrid from '@/components/user/record-grid';
import { Button } from '@/components/ui/button';
import { browse } from '@/routes/catalogue';
import type {
    CatalogueCard,
    CatalogueFacets,
    CatalogueProject,
} from '@/types/catalogue';
import CatalogueHero from './partials/catalogue-hero';
import SectionHeading from './partials/section-heading';

type Props = {
    latest: CatalogueCard[];
    projects: CatalogueProject[];
    genres: CatalogueFacets['genres'];
    total: number;
};

/**
 * The catalogue home: a hero with search, genres and the newest video, then
 * the latest published videos and current and recent events.
 */
export default function Home({ latest, projects, genres, total }: Props) {
    const [featured = null, ...rest] = latest;

    return (
        <>
            <Head title="Catalogue" />
            <div className="grid gap-14">
                <CatalogueHero
                    featured={featured}
                    genres={genres}
                    total={total}
                />

                <section className="grid gap-6">
                    <SectionHeading
                        title="Latest videos"
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                className="group rounded-full bg-background"
                                asChild
                            >
                                <Link href={browse()}>
                                    Browse all
                                    <ArrowRight
                                        className="transition-transform group-hover:translate-x-0.5"
                                        aria-hidden
                                    />
                                </Link>
                            </Button>
                        }
                    />
                    <RecordGrid
                        records={rest}
                        empty={
                            featured
                                ? 'More videos will appear here as they are published.'
                                : 'Nothing has been published yet.'
                        }
                    />
                </section>

                {projects.length > 0 && (
                    <section className="grid gap-6">
                        <SectionHeading title="Events and programmes" />
                        <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
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
