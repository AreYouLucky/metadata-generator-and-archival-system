/** Summed usage for a period (admin Usage page and overview). */
export type UsageNumbers = {
    minutes: number;
    input_tokens: number;
    output_tokens: number;
    vision_frames: number;
    /** Estimated, in US dollars. */
    cost: number;
};

export type EncoderUsage = UsageNumbers & {
    user_id: number | null;
    name: string;
};

export type RecordUsage = UsageNumbers & {
    record_id: string;
    title: string | null;
    accession_number: string | null;
    encoder: string | null;
    deleted: boolean;
};

export type DailyUsage = { date: string; minutes: number; cost: number };

export type UsageFilters = {
    from: string;
    to: string;
    encoder: number | null;
};

/** Today's processing allowance for the signed-in encoder; null limit = none. */
export type DailyLimit = {
    limit: number | null;
    used: number;
    remaining: number | null;
};
