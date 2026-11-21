import { router } from '@inertiajs/react';
import { useMutation } from '@tanstack/react-query';
import { http } from '@/lib/utilities';
import { store, update } from '@/routes/admin/users';
import type { UserRole } from '@/types';

export type CreateUserInput = {
    name: string;
    email: string;
    role: Exclude<UserRole, 'user'>;
    password: string;
    daily_processing_minutes_limit: number | null;
};

export type UpdateUserInput = {
    role?: UserRole;
    is_active?: boolean;
    daily_processing_minutes_limit?: number | null;
};

type UserSaved = { id: number; message: string };

const reloadUsers = () => router.reload({ only: ['users'] });

/**
 * Admin: create an encoder or admin account, then refresh the user list.
 */
export function useCreateUser() {
    return useMutation({
        mutationFn: async (input: CreateUserInput) =>
            (await http.post<UserSaved>(store.url(), input)).data,
        onSuccess: reloadUsers,
    });
}

/**
 * Admin: change a user's role, active status or daily limit.
 */
export function useUpdateUser() {
    return useMutation({
        mutationFn: async ({
            userId,
            ...input
        }: UpdateUserInput & { userId: number }) =>
            (await http.patch<UserSaved>(update.url(userId), input)).data,
        onSuccess: reloadUsers,
    });
}
