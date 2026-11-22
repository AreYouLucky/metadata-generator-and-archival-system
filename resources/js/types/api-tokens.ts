/** Public API tokens (Phase 7). */
export type ApiTokenAbility = 'records:read' | 'records:create';

export type ApiToken = {
    id: number;
    name: string;
    abilities: ApiTokenAbility[];
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string | null;
    expired: boolean;
};

export type ApiTokenAbilityOption = { value: ApiTokenAbility; label: string };

export type ApiTokenInput = {
    name: string;
    abilities: ApiTokenAbility[];
    /** null = never expires. */
    expires_in_days: number | null;
};

export type ApiLimits = {
    requests_per_minute: number;
    max_upload_mb: number;
};
