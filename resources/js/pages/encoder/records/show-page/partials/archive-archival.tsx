import { Input } from '@/components/ui/input';
import { useArchiveForm } from '@/hooks/use-archive-form';
import { useUpdateRecord } from '@/hooks/use-archive';
import type { Archive, ArchiveOptions } from '@/types/archive';
import ArchiveField from './archive-field';
import ArchiveSaveBar from './archive-save-bar';
import ArchiveSelect from './archive-select';

type Props = {
    recordId: string;
    archive: Archive;
    options: ArchiveOptions;
    canEdit: boolean;
};

/**
 * Accession number, security classification, retention and disposition.
 * Only unclassified records can ever be public; nothing is destroyed on the
 * disposition date, it only puts the record on the admins' review list.
 */
export default function ArchiveArchival({
    recordId,
    archive,
    options,
    canEdit,
}: Props) {
    const { values, change, dirty, errors, feedback } = useArchiveForm(
        archive.archival,
    );
    const save = useUpdateRecord(recordId);

    return (
        <div className="grid gap-4">
            <ArchiveField
                label="Accession number"
                htmlFor="archive-accession"
                hint="Assigned at ingest; must stay unique."
                error={errors.accession_number}
            >
                <Input
                    id="archive-accession"
                    value={values.accession_number ?? ''}
                    maxLength={64}
                    className="font-mono"
                    disabled={!canEdit}
                    onChange={(event) =>
                        change({ accession_number: event.target.value })
                    }
                />
            </ArchiveField>
            <ArchiveField
                label="Security classification"
                htmlFor="archive-classification"
                hint="Anything above unclassified keeps the record out of every public listing, export and API."
                error={errors.security_classification}
            >
                <ArchiveSelect
                    id="archive-classification"
                    value={values.security_classification}
                    options={options.classifications}
                    disabled={!canEdit}
                    onChange={(value) =>
                        change({
                            security_classification: value ?? 'unclassified',
                        })
                    }
                />
            </ArchiveField>
            <div className="grid gap-4 @md:grid-cols-2">
                <ArchiveField
                    label="Retention period"
                    htmlFor="archive-retention"
                    hint="e.g. Permanent, 10 years"
                    error={errors.retention_period}
                >
                    <Input
                        id="archive-retention"
                        value={values.retention_period ?? ''}
                        maxLength={100}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                retention_period: event.target.value || null,
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField label="Archival value" htmlFor="archive-value">
                    <ArchiveSelect
                        id="archive-value"
                        value={values.archival_value}
                        options={options.archivalValues}
                        emptyLabel="Not set"
                        disabled={!canEdit}
                        onChange={(value) => change({ archival_value: value })}
                    />
                </ArchiveField>
                <ArchiveField
                    label="Disposition date"
                    htmlFor="archive-disposition-date"
                    error={errors.disposition_date}
                >
                    <Input
                        id="archive-disposition-date"
                        type="date"
                        value={values.disposition_date ?? ''}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                disposition_date: event.target.value || null,
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Then"
                    htmlFor="archive-disposition-action"
                    error={errors.disposition_action}
                >
                    <ArchiveSelect
                        id="archive-disposition-action"
                        value={values.disposition_action}
                        options={options.dispositionActions}
                        emptyLabel="Not set"
                        disabled={!canEdit}
                        onChange={(value) =>
                            change({ disposition_action: value })
                        }
                    />
                </ArchiveField>
            </div>
            {canEdit && (
                <ArchiveSaveBar
                    dirty={dirty}
                    pending={save.isPending}
                    onSave={() =>
                        save.mutate(values, feedback('Archival details saved.'))
                    }
                />
            )}
        </div>
    );
}
