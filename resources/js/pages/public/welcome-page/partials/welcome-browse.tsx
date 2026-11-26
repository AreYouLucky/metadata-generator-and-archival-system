import {
    Building2,
    CalendarRange,
    Clapperboard,
    Layers,
    MapPinned,
    Tags,
} from 'lucide-react';
import FoldHeading from './fold-heading';
import Reveal from './reveal';
import SearchPreview from './search-preview';
import WelcomeFold from './welcome-fold';

const CATEGORIES = [
    {
        icon: Clapperboard,
        title: 'Event',
        example: 'Meetings, speeches, programs and celebrations',
    },
    {
        icon: Layers,
        title: 'Series',
        example: 'Every day or session of a longer celebration',
    },
    {
        icon: CalendarRange,
        title: 'Date',
        example: 'By year and month recorded',
    },
    {
        icon: MapPinned,
        title: 'Place',
        example: 'Region, province, city and barangay',
    },
    {
        icon: Tags,
        title: 'Genre and type',
        example: 'Highlights, interviews, documentaries, raw footage',
    },
    {
        icon: Building2,
        title: 'Agency',
        example: 'The team or studio that recorded it',
    },
];

export default function WelcomeBrowse() {
    return (
        <WelcomeFold
            id="browse"
            className="border bg-linear-to-br from-yellow-100 via-yellow-50 to-sky-100"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgb(253_224_71/0.45),transparent_50%)]"
            />
            <div className="relative grid grid-cols-1 items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] lg:gap-16 xl:gap-24">
                <div className="space-y-8">
                    <FoldHeading
                        eyebrow="Browse"
                        title="Find any moment, the way you remember it"
                        description="Search by title, description or keyword, or browse by event, date and place. Every video plays with captions, chapters, a synced transcript and key frames."
                    />
                    <Reveal delay={150}>
                        <SearchPreview />
                    </Reveal>
                </div>

                <ul className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
                    {CATEGORIES.map((category, index) => (
                        <li key={category.title}>
                            <Reveal delay={index * 90} className="h-full">
                                <div className="group h-full rounded-2xl border bg-white p-4 transition duration-300 sm:p-6 hover:-translate-y-1 hover:border-brand/40 hover:shadow-lg hover:shadow-brand/10">
                                    <span className="flex size-9 items-center justify-center rounded-xl bg-brand-muted sm:size-10 text-brand transition-all duration-300 group-hover:scale-110 group-hover:bg-brand group-hover:text-brand-foreground">
                                        <category.icon className="size-5" />
                                    </span>
                                    <h3 className="mt-4 text-sm font-semibold sm:mt-5 sm:text-base">
                                        {category.title}
                                    </h3>
                                    <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                                        {category.example}
                                    </p>
                                </div>
                            </Reveal>
                        </li>
                    ))}
                </ul>
            </div>
        </WelcomeFold>
    );
}
