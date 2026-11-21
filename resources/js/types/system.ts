export type AiServiceHealth = {
    reachable: boolean;
    status: string | null;
    version: string | null;
    /** The transcription worker's GPU (published by the running worker). */
    gpu: boolean | null;
    gpu_name: string | null;
    latency_ms: number | null;
    error: string | null;
};

export type PingTarget = 'broadcast' | 'ai_service';

export type PingSource = 'laravel' | 'ai-worker';

export type SystemPingAccepted = {
    ping_id: string;
    job_id?: string;
};

export type SystemPingEvent = {
    ping_id: string;
    source: PingSource;
    received_at: string;
};
