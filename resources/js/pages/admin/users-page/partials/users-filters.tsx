import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/users';
import type { UserRole } from '@/types/auth';
import type { UserFilters } from '@/types/users';

const ALL = 'all';

export default function UsersFilters({ filters }: { filters: UserFilters }) {
    const [search, setSearch] = useState(filters.q);
    const firstRender = useRef(true);

    const apply = (changes: Partial<UserFilters>) => {
        const next = { ...filters, q: search, ...changes };

        router.get(
            index.url(),
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => apply({ q: search }), 350);

        return () => clearTimeout(timer);
        // Only the search text triggers the debounced visit.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <div className="flex flex-wrap gap-3">
            <div className="relative w-full sm:w-72">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden
                />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search name or email"
                    aria-label="Search users"
                    className="pl-9"
                />
            </div>
            <Select
                value={filters.role ?? ALL}
                onValueChange={(value) =>
                    apply({ role: value === ALL ? null : (value as UserRole) })
                }
            >
                <SelectTrigger className="w-40" aria-label="Role">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All roles</SelectItem>
                    <SelectItem value="admin">Admins</SelectItem>
                    <SelectItem value="encoder">Encoders</SelectItem>
                    <SelectItem value="user">Viewers</SelectItem>
                </SelectContent>
            </Select>
        </div>
    );
}
