import {
    Building2,
    CalendarRange,
    Clapperboard,
    Layers,
    MapPinned,
    Tags,
} from 'lucide-react';
import Reveal from './reveal';
import SearchPreview from './search-preview';
import WelcomeFold from './welcome-fold';

const CATEGORIES = [
    {
        icon: Clapperboard,
        title: 'Event',
        example: 'Conferences, launches, award ceremonies',
    },
    {
        icon: Layers,
        title: 'Series',
        example: 'Each day or session of a multi-day event',
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
        example: 'Documentary, interview, highlights, raw footage',
    },
    {
        icon: Building2,
        title: 'Agency',
        example: 'The office or unit that produced it',
    },
];

export default function WelcomeBrowse() {
    return (
        <WelcomeFold id="browse" className="border bg-brand-muted">
            <div
                aria-hidden
                className="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-brand/15 blur-3xl"
            />
            <div className="relative grid items-center gap-10 lg:grid-cols-[1fr_1.4fr] lg:gap-16">
                <Reveal className="space-y-6">
                    <div className="space-y-3">
                        <p className="text-sm font-medium text-brand">Browse</p>
                        <h2 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                            Find what you are looking for
                        </h2>
                        <p className="text-lg text-muted-foreground">
                            Every published record is filed under the event it
                            belongs to and the place it was recorded, so you can
                            browse the catalogue the way you remember it.
                        </p>
                    </div>
                    <SearchPreview />
                </Reveal>

                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {CATEGORIES.map((category, index) => (
                        <li key={category.title}>
                            <Reveal delay={index * 90} className="h-full">
                                <div className="group h-full rounded-2xl border bg-background p-5 transition duration-300 hover:-translate-y-1 hover:border-brand/40 hover:shadow-lg hover:shadow-brand/10">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-brand-muted text-brand transition-all duration-300 group-hover:scale-110 group-hover:bg-brand group-hover:text-brand-foreground">
                                        <category.icon className="size-5" />
                                    </span>
                                    <h3 className="mt-4 font-semibold">
                                        {category.title}
                                    </h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
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
