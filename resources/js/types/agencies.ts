export type AgencySummary = {
    id: number;
    name: string;
    acronym: string | null;
};

/** A top-level agency with its production units (divisions). */
export type AgencyOption = AgencySummary & {
    units: AgencySummary[];
};

/** Agencies page rows (every encoder can browse; admins manage). */
export type AdminAgency = AgencySummary & {
    records_count: number;
    projects_count: number;
    units: (AgencySummary & { records_count: number })[];
};
