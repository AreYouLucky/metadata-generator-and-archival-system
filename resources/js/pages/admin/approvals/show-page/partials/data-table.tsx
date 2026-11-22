import type { ReactNode } from 'react';

type Props = {
    title: string;
    columns: { label: string; className?: string }[];
    /** One array of cells per row, in column order. */
    rows: { key: string | number; cells: ReactNode[] }[];
    empty: string;
};

/**
 * A titled table with column headers, for lists (credits, subjects, files,
 * chapters…). Scrolls sideways on narrow screens.
 */
export default function DataTable({ title, columns, rows, empty }: Props) {
    return (
        <section className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <caption className="border-b bg-muted/40 px-4 py-2.5 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {title}
                </caption>
                <thead className="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.label}
                                scope="col"
                                className={`px-4 py-2 font-medium ${column.className ?? ''}`}
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="px-4 py-6 text-center text-muted-foreground"
                            >
                                {empty}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr key={row.key} className="align-top">
                                {row.cells.map((cell, index) => (
                                    <td
                                        key={index}
                                        className={`px-4 py-2 break-words ${columns[index]?.className ?? ''}`}
                                    >
                                        {cell ?? '—'}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </section>
    );
}
