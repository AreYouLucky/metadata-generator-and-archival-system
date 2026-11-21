import { Head, usePage } from '@inertiajs/react';
import SimplePagination from '@/components/all/simple-pagination';
import Heading from '@/components/heading';
import { index } from '@/routes/admin/users';
import type { Paginated } from '@/types/pagination';
import type { AdminUserRow, UserFilters } from '@/types/users';
import CreateUserDialog from './partials/create-user-dialog';
import UserRow from './partials/user-row';
import UsersFilters from './partials/users-filters';

type Props = {
    users: Paginated<AdminUserRow>;
    filters: UserFilters;
    defaultDailyMinutes: number;
};

export default function Users({ users, filters, defaultDailyMinutes }: Props) {
    const currentUserId = usePage().props.auth.user?.id;

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Create encoder and admin accounts, change roles and deactivate accounts. Viewers sign up themselves."
                    />
                    <CreateUserDialog
                        defaultDailyMinutes={defaultDailyMinutes}
                    />
                </div>

                <UsersFilters filters={filters} />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Name
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Role
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Records
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Daily minutes
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {users.data.map((user) => (
                                <UserRow
                                    key={user.id}
                                    user={user}
                                    isSelf={user.id === currentUserId}
                                    defaultDailyMinutes={defaultDailyMinutes}
                                />
                            ))}
                        </tbody>
                    </table>
                    {users.data.length === 0 && (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No users match.
                        </p>
                    )}
                </div>

                <SimplePagination page={users} />
            </div>
        </>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
