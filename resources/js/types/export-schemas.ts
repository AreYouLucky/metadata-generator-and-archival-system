/**
 * Custom JSON export schemas: dot-paths kept from the §10 document
 * (App\Exporters\ExportSchemaFields on the server).
 */
export type ExportSchema = {
    id: number;
    name: string;
    fields: string[];
    is_default: boolean;
};

export type ExportFieldGroup = {
    group: string;
    fields: { path: string; label: string }[];
};

export type ExportSchemaInput = Omit<ExportSchema, 'id'>;
