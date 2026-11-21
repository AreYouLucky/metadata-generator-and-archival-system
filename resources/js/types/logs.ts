export type LogType = 'audit' | 'access';

/** One row of the audit or access log, in the shape both share. */
export type LogEntry = {
    id: number;
    action: string;
    record: { id: string; title: string | null; deleted: boolean };
    user: string | null;
    at: string;
    /** Audit: review notes or checksum results. Access: the IP address. */
    details: string | null;
};

export type LogFilters = {
    type: LogType;
    action: string | null;
};
