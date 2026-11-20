import {
    Building2,
    CalendarRange,
    Clapperboard,
    Layers,
    MapPinned,
    Tags,
} from 'lucide-react';

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
        <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div className="grid gap-10 lg:grid-cols-[1fr_1.6fr] lg:gap-16">
                <div className="space-y-3">
                    <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Find what you are looking for
                    </h2>
                    <p className="text-muted-foreground">
                        Every published record is filed under the event it
                        belongs to and the place it was recorded, so you can
                        browse the catalogue the way you remember it.
                    </p>
                </div>

                <ul className="grid gap-3 sm:grid-cols-2">
                    {CATEGORIES.map((category) => (
                        <li
                            key={category.title}
                            className="flex gap-3 rounded-xl border p-4"
                        >
                            <category.icon className="mt-0.5 size-5 shrink-0 text-brand" />
                            <div>
                                <h3 className="font-medium">
                                    {category.title}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {category.example}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
