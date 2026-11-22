import { Link } from '@inertiajs/react';
import { browse } from '@/routes/catalogue';
import { show as showProject } from '@/routes/catalogue/projects';
import { show as showSeries } from '@/routes/catalogue/series';
import { formatDate, formatPlace, languageName } from '@/lib/utilities';
import type { WatchRecord } from '@/types/catalogue';

/**
 * The public catalogue fields of a record (PROJECT_SPEC §1): never rights
 * notes, provenance, checksums, unconfirmed suggestions or classification.
 */
export default function WatchDetails({ record }: { record: WatchRecord }) {
    const place = formatPlace(record.location, record.place);
    const recorded = record.date_recorded
        ? `${formatDate(record.date_recorded)}${record.date_recorded_precision === 'circa' ? ' (circa)' : ''}`
        : null;

    const rows: [string, React.ReactNode][] = [];

    if (recorded) {
        rows.push(['Recorded', recorded]);
    }

    if (record.project) {
        rows.push([
            'Event',
            <Link
                key="project"
                href={showProject(record.project.id)}
                className="text-brand hover:underline"
            >
                {record.project.title}
            </Link>,
        ]);
    }

    if (record.series) {
        rows.push([
            'Series',
            <Link
                key="series"
                href={showSeries(record.series.id)}
                className="text-brand hover:underline"
            >
                {record.series.title}
            </Link>,
        ]);
    }

    if (record.genre) {
        rows.push([
            'Genre',
            <Link
                key="genre"
                href={browse({ query: { genre: record.genre.slug } })}
                className="text-brand hover:underline"
            >
                {record.genre.name}
            </Link>,
        ]);
    }

    if (record.video_type) {
        rows.push([
            'Type',
            <Link
                key="type"
                href={browse({ query: { type: record.video_type.slug } })}
                className="text-brand hover:underline"
            >
                {record.video_type.name}
            </Link>,
        ]);
    }

    if (record.agency) {
        rows.push([
            'Produced by',
            [record.agency.name, record.division].filter(Boolean).join(' › '),
        ]);
    }

    if (place) {
        rows.push(['Place', place]);
    }

    if (record.language) {
        rows.push(['Language', languageName(record.language)]);
    }

    return (
        <div className="grid gap-6">
            <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                {rows.map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-xs text-muted-foreground">
                            {label}
                        </dt>
                        <dd>{value}</dd>
                    </div>
                ))}
            </dl>

            {record.credits.length > 0 && (
                <section className="grid gap-2">
                    <h2 className="text-sm font-medium">Credits</h2>
                    <ul className="grid gap-1 text-sm sm:grid-cols-2">
                        {record.credits.map((credit, index) => (
                            <li key={index}>
                                <span className="text-muted-foreground">
                                    {credit.role}:
                                </span>{' '}
                                {credit.name}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {record.subjects.length + record.keywords.length > 0 && (
                <section className="grid gap-2">
                    <h2 className="text-sm font-medium">
                        Subjects and keywords
                    </h2>
                    <ul className="flex flex-wrap gap-1.5">
                        {record.subjects.map((subject) => (
                            <li key={`s-${subject.subject}`}>
                                <Link
                                    href={browse({
                                        query: { q: subject.subject },
                                    })}
                                    className="inline-block rounded-full border px-2.5 py-0.5 text-xs hover:border-brand/50"
                                >
                                    {subject.subject}
                                </Link>
                            </li>
                        ))}
                        {record.keywords.map((keyword) => (
                            <li key={`k-${keyword}`}>
                                <Link
                                    href={browse({ query: { q: keyword } })}
                                    className="inline-block rounded-full border px-2.5 py-0.5 text-xs text-muted-foreground hover:border-brand/50"
                                >
                                    {keyword}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {(record.license || record.usage_restrictions) && (
                <section className="grid gap-1 rounded-xl border p-4 text-sm">
                    <h2 className="font-medium">Licence and use</h2>
                    {record.license && (
                        <p>
                            {record.license_url ? (
                                <a
                                    href={record.license_url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="text-brand hover:underline"
                                >
                                    {record.license}
                                </a>
                            ) : (
                                record.license
                            )}
                        </p>
                    )}
                    {record.usage_restrictions && (
                        <p className="text-muted-foreground">
                            {record.usage_restrictions}
                        </p>
                    )}
                </section>
            )}
        </div>
    );
}
