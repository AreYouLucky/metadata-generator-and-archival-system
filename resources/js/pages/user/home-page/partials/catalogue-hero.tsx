import FilmSprockets from '@/components/all/film-sprockets';
import ScribbleUnderline from '@/components/all/scribble-underline';
import { formatNumber } from '@/lib/utilities';
import type { CatalogueCard, CatalogueFacets } from '@/types/catalogue';
import FeaturedRecord from './featured-record';
import GenreChips from './genre-chips';
import HeroSearch from './hero-search';

type Props = {
    featured: CatalogueCard | null;
    genres: CatalogueFacets['genres'];
    total: number;
};

/**
 * The catalogue's opening band: headline, record count, a large search, the
 * genre shortcuts and the newest video beside them.
 */
export default function CatalogueHero({ featured, genres, total }: Props) {
    return (
        <section className="relative overflow-hidden rounded-3xl border bg-white/85 shadow-sm backdrop-blur-sm">
            <FilmSprockets className="h-3 text-sky-100" />

            <div className="grid items-center gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,40rem)] lg:gap-12 lg:p-12 2xl:grid-cols-[minmax(0,1fr)_minmax(0,48rem)] 2xl:gap-20">
                <div className="grid content-center gap-6">
                    <p className="flex items-center gap-3 text-xs font-semibold tracking-[0.18em] text-brand uppercase">
                        <span className="h-px w-8 bg-brand" aria-hidden />
                        Public catalogue
                    </p>
                    <div className="grid gap-4">
                        <h1 className="text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl 2xl:text-6xl">
                            The video{' '}
                            <span className="relative inline-block whitespace-nowrap text-brand">
                                archive.
                                <ScribbleUnderline />
                            </span>
                        </h1>
                        <p className="max-w-xl text-base leading-relaxed text-pretty text-muted-foreground sm:text-lg">
                            {total === 0
                                ? 'Published videos will appear here.'
                                : `${formatNumber(total)} published ${total === 1 ? 'video' : 'videos'}, each transcribed, described and filed by event, series, date and place.`}
                        </p>
                    </div>

                    <HeroSearch />

                    {genres.length > 0 && <GenreChips genres={genres} />}
                </div>

                {featured && <FeaturedRecord record={featured} />}
            </div>

            <FilmSprockets className="h-3 text-sky-100" />
        </section>
    );
}
