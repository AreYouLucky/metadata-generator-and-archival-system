import { router } from '@inertiajs/react';
import { Download, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { exportMethod, index } from '@/routes/admin/usage';
import type { UsageFilters as Filters } from '@/types/usage';

type Props = {
    filters: Filters;
    encoderName: string | null;
};

/**
 * Date range (this month by default), the encoder filter chip, and the CSV
 * download for the same range.
 */
export default function UsageFilters({ filters, encoderName }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const query = {
        from,
        to,
        ...(filters.encoder ? { encoder: filters.encoder } : {}),
    };

    const apply = (event: FormEvent) => {
        event.preventDefault();
        router.get(index.url(), query, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={apply}
            className="flex flex-wrap items-end gap-3 rounded-xl border p-4"
        >
            <div className="grid gap-1.5">
                <Label htmlFor="usage-from">From</Label>
                <Input
                    id="usage-from"
                    type="date"
                    value={from}
                    max={to}
                    onChange={(event) => setFrom(event.target.value)}
                />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="usage-to">To</Label>
                <Input
                    id="usage-to"
                    type="date"
                    value={to}
                    min={from}
                    onChange={(event) => setTo(event.target.value)}
                />
            </div>
            <Button type="submit">Show</Button>
            {encoderName && (
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() =>
                        router.get(
                            index.url(),
                            { from, to },
                            { preserveScroll: true },
                        )
                    }
                >
                    {encoderName}
                    <X aria-label="Show all encoders" />
                </Button>
            )}
            <Button variant="outline" className="ml-auto" asChild>
                <a href={exportMethod.url({ query })}>
                    <Download aria-hidden />
                    Download CSV
                </a>
            </Button>
        </form>
    );
}
