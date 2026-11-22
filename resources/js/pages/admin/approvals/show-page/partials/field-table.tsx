import type { ReactNode } from 'react';

export type FieldRow = [label: string, value: ReactNode];

type Props = {
    title: string;
    rows: FieldRow[];
};

/**
 * A titled two-column table of fields and their values. Empty values read
 * "Not set", so the reviewer sees what is missing.
 */
export default function FieldTable({ title, rows }: Props) {
    return (
        <section className="overflow-hidden rounded-xl border">
            <table className="w-full text-sm">
                <caption className="border-b bg-muted/40 px-4 py-2.5 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {title}
                </caption>
                <tbody className="divide-y">
                    {rows.map(([label, value]) => {
                        const empty =
                            value === null ||
                            value === undefined ||
                            value === '' ||
                            (Array.isArray(value) && value.length === 0);

                        return (
                            <tr key={label} className="align-top">
                                <th
                                    scope="row"
                                    className="w-44 px-4 py-2 text-left font-normal text-muted-foreground sm:w-56"
                                >
                                    {label}
                                </th>
                                <td
                                    className={`px-4 py-2 break-words whitespace-pre-line ${empty ? 'text-muted-foreground italic' : ''}`}
                                >
                                    {empty ? 'Not set' : value}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </section>
    );
}
