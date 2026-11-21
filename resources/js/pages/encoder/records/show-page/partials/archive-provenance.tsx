import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useArchiveForm } from '@/hooks/use-archive-form';
import { useSaveProvenance } from '@/hooks/use-archive';
import type {
    Archive,
    ArchiveOptions,
    ArchiveProvenance as Provenance,
} from '@/types/archive';
import ArchiveField from './archive-field';
import ArchiveSaveBar from './archive-save-bar';
import ArchiveSelect from './archive-select';
import AuditTimeline from './audit-timeline';
import FixityHistory from './fixity-history';

type Props = {
    recordId: string;
    archive: Archive;
    options: ArchiveOptions;
    canEdit: boolean;
};

const EMPTY: Provenance = {
    source_carrier: null,
    carrier_label: null,
    carrier_id: null,
    transfer_date: null,
    transfer_facility: null,
    transfer_operator: null,
    transfer_deck: null,
    transfer_settings: null,
    notes: null,
};

/**
 * What has happened to the video: where it came from (carrier and transfer),
 * the fixity checks with "Check now", and the audit log.
 */
export default function ArchiveProvenance({
    recordId,
    archive,
    options,
    canEdit,
}: Props) {
    const { values, change, dirty, errors, feedback } = useArchiveForm(
        archive.provenance ?? EMPTY,
    );
    const save = useSaveProvenance(recordId);
    const text = (value: string) => (value.trim() === '' ? null : value);
    const field = (key: keyof Provenance, label: string) => (
        <ArchiveField
            label={label}
            htmlFor={`provenance-${key}`}
            error={errors[key]}
        >
            <Input
                id={`provenance-${key}`}
                value={values[key] ?? ''}
                maxLength={250}
                disabled={!canEdit}
                onChange={(event) =>
                    change({ [key]: text(event.target.value) })
                }
            />
        </ArchiveField>
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-4 @md:grid-cols-2">
                <ArchiveField
                    label="Source carrier"
                    htmlFor="provenance-carrier"
                >
                    <ArchiveSelect
                        id="provenance-carrier"
                        value={values.source_carrier}
                        options={options.sourceCarriers}
                        emptyLabel="Not recorded"
                        disabled={!canEdit}
                        onChange={(value) => change({ source_carrier: value })}
                    />
                </ArchiveField>
                {field('carrier_label', 'Carrier label')}
                {field('carrier_id', 'Carrier ID')}
                <ArchiveField
                    label="Transfer date"
                    htmlFor="provenance-transfer-date"
                    error={errors.transfer_date}
                >
                    <Input
                        id="provenance-transfer-date"
                        type="date"
                        value={values.transfer_date ?? ''}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                transfer_date: event.target.value || null,
                            })
                        }
                    />
                </ArchiveField>
                {field('transfer_facility', 'Facility')}
                {field('transfer_operator', 'Operator')}
                {field('transfer_deck', 'Deck / reader')}
            </div>
            <ArchiveField
                label="Transfer settings"
                htmlFor="provenance-settings"
                error={errors.transfer_settings}
            >
                <Textarea
                    id="provenance-settings"
                    rows={2}
                    maxLength={2000}
                    value={values.transfer_settings ?? ''}
                    disabled={!canEdit}
                    onChange={(event) =>
                        change({ transfer_settings: text(event.target.value) })
                    }
                />
            </ArchiveField>
            <ArchiveField
                label="Notes"
                htmlFor="provenance-notes"
                error={errors.notes}
            >
                <Textarea
                    id="provenance-notes"
                    rows={2}
                    maxLength={5000}
                    value={values.notes ?? ''}
                    disabled={!canEdit}
                    onChange={(event) =>
                        change({ notes: text(event.target.value) })
                    }
                />
            </ArchiveField>
            {canEdit && (
                <ArchiveSaveBar
                    dirty={dirty}
                    pending={save.isPending}
                    onSave={() =>
                        save.mutate(values, feedback('Provenance saved.'))
                    }
                />
            )}

            <FixityHistory recordId={recordId} entries={archive.fixity} />
            <AuditTimeline entries={archive.audit} />
        </div>
    );
}
