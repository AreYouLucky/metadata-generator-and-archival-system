import ClassificationBadge from '@/components/all/classification-badge';
import {
    formatDate,
    formatEnumLabel,
    formatPlace,
    formatTimestamp,
    languageName,
} from '@/lib/utilities';
import type { ApprovalRecord } from '@/types/reviews';
import ConfirmSuggestionsButton from './confirm-suggestions-button';
import DataTable from './data-table';
import FieldTable from './field-table';

/**
 * The Overview tab: the catalogue record, its rights and its credits, as
 * tables.
 */
export default function RecordSummary({ record }: { record: ApprovalRecord }) {
    const language = record.language
        ? `${languageName(record.language)}${record.language_confidence !== null ? ` (detected, ${Math.round(record.language_confidence * 100)}% confident)` : ''}`
        : null;
    const recorded = record.date_recorded
        ? `${formatDate(record.date_recorded)}${record.date_recorded_precision && record.date_recorded_precision !== 'day' ? ` (${record.date_recorded_precision})` : ''}`
        : null;

    return (
        <div className="grid gap-4">
            <FieldTable
                title="Record"
                rows={[
                    ['Title', record.title],
                    ['Alternative title', record.alternative_title],
                    ['Description', record.description],
                    ['AI summary', record.summary],
                    ['Accession number', record.accession_number],
                    ['Project', record.project],
                    ['Series', record.series],
                    ['Agency', record.agency],
                    ['Production unit', record.division],
                    ['Genre', record.genre],
                    ['Video type', record.video_type],
                    ['Language', language],
                    ['Date recorded', recorded],
                    ['Place', formatPlace(record.location, record.place)],
                    [
                        'Duration',
                        record.duration !== null
                            ? formatTimestamp(record.duration)
                            : null,
                    ],
                    ['Resolution', record.resolution],
                    [
                        'Classification',
                        <ClassificationBadge
                            key="classification"
                            classification={record.classification}
                        />,
                    ],
                    ['Encoder', record.creator],
                    [
                        'Unconfirmed AI suggestions',
                        record.pending_suggestions > 0 ? (
                            <span key="pending">
                                {record.pending_suggestions} subjects and
                                keywords (not shown publicly until confirmed)
                                <ConfirmSuggestionsButton
                                    recordId={record.id}
                                />
                            </span>
                        ) : (
                            'None'
                        ),
                    ],
                ]}
            />

            <FieldTable
                title="Rights summary"
                rows={
                    record.rights === null
                        ? [['Rights', null]]
                        : [
                              [
                                  'Access level',
                                  formatEnumLabel(record.rights.access_level),
                              ],
                              [
                                  'Permission',
                                  formatEnumLabel(
                                      record.rights.permission_status,
                                  ),
                              ],
                              [
                                  'Copyright holder',
                                  record.rights.copyright_holder,
                              ],
                              ['Licence', record.rights.license],
                              [
                                  'Embargo until',
                                  record.rights.embargo_until
                                      ? formatDate(record.rights.embargo_until)
                                      : null,
                              ],
                              [
                                  'Usage restrictions',
                                  record.rights.usage_restrictions,
                              ],
                          ]
                }
            />

            <DataTable
                title="Credits"
                columns={[
                    { label: 'Role', className: 'w-56' },
                    { label: 'Name' },
                ]}
                rows={record.contributors.map((credit, index) => ({
                    key: index,
                    cells: [credit.role, credit.name],
                }))}
                empty="No credits."
            />
        </div>
    );
}
