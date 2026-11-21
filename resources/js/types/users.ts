import type { UserRole } from '@/types/auth';

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
    daily_processing_minutes_limit: number | null;
    records_count: number;
    created_at: string | null;
};

export type UserFilters = {
    q: string;
    role: UserRole | null;
};
