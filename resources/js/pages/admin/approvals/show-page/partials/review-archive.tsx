import {
    formatBytes,
    formatDate,
    formatDateTime,
    formatEnumLabel,
    formatTimestamp,
    languageName,
} from '@/lib/utilities';
import type { Archive } from '@/types/archive';
import type { ApprovalRecord } from '@/types/reviews';
import DataTable from './data-table';
import FieldTable from './field-table';

type Props = {
    archive: Archive;
    /** Names for the ids the archive holds (genre, agency, place…). */
    record: ApprovalRecord;
};

const label = (value: string | null) => (value ? formatEnumLabel(value) : null);

/**
 * The whole archival record (PROJECT_SPEC §11), read-only, as tables:
 * descriptive, location, subjects, keywords, rights, provenance, archival,
 * files with their technical metadata, structure and fixity.
 */
export default function ReviewArchive({ archive, record }: Props) {
    const { descriptive, location, rights, provenance, archival } = archive;

    return (
        <div className="grid gap-4">
            <FieldTable
                title="Descriptive"
                rows={[
                    ['Title', descriptive.title],
                    ['Alternative title', descriptive.alternative_title],
                    ['Description', descriptive.description],
                    ['Genre', record.genre],
                    ['Video type', record.video_type],
                    [
                        'Language',
                        descriptive.language
                            ? languageName(descriptive.language)
                            : null,
                    ],
                    ['Country of origin', descriptive.country_origin],
                    [
                        'Date recorded',
                        descriptive.date_recorded
                            ? [
                                  formatDate(descriptive.date_recorded),
                                  descriptive.date_recorded_precision &&
                                      `precision: ${descriptive.date_recorded_precision}`,
                                  descriptive.date_recorded_source &&
                                      `source: ${label(descriptive.date_recorded_source)}`,
                              ]
                                  .filter(Boolean)
                                  .join(' · ')
                            : null,
                    ],
                    [
                        'Date published',
                        descriptive.date_published
                            ? formatDate(descriptive.date_published)
                            : null,
                    ],
                    ['Season', descriptive.season_number],
                    ['Episode', descriptive.episode_number],
                    ['Project', record.project],
                    ['Series', record.series],
                ]}
            />

            <FieldTable
                title="Location and credits"
                rows={[
                    ['Venue', location.location],
                    ['Place', record.place],
                    [
                        'GPS',
                        location.latitude !== null &&
                        location.longitude !== null
                            ? `${location.latitude}, ${location.longitude}${location.location_source ? ` (${label(location.location_source)})` : ''}`
                            : null,
                    ],
                    ['Agency', record.agency],
                    ['Production unit', record.division],
                ]}
            />

            <DataTable
                title="Subjects"
                columns={[
                    { label: 'Subject' },
                    { label: 'Type', className: 'w-32' },
                    { label: 'Source', className: 'w-32' },
                    { label: 'Status', className: 'w-32' },
                ]}
                rows={archive.subjects.map((subject) => ({
                    key: subject.id,
                    cells: [
                        subject.subject,
                        formatEnumLabel(subject.type),
                        formatEnumLabel(subject.source),
                        subject.is_confirmed ? 'Confirmed' : 'Suggestion',
                    ],
                }))}
                empty="No subjects."
            />

            <DataTable
                title="Keywords"
                columns={[
                    { label: 'Keyword' },
                    { label: 'Source', className: 'w-32' },
                    { label: 'Status', className: 'w-32' },
                ]}
                rows={archive.keywords.map((keyword) => ({
                    key: keyword.id,
                    cells: [
                        keyword.keyword,
                        formatEnumLabel(keyword.source),
                        keyword.is_confirmed ? 'Confirmed' : 'Suggestion',
                    ],
                }))}
                empty="No keywords."
            />

            <FieldTable
                title="Rights"
                rows={
                    rights === null
                        ? [['Rights', 'Not filled in (public by default)']]
                        : [
                              ['Copyright holder', rights.copyright_holder],
                              ['Copyright year', rights.copyright_year],
                              ['Rights statement', rights.rights_statement],
                              ['Licence', rights.license],
                              ['Licence URL', rights.license_url],
                              ['Access level', label(rights.access_level)],
                              ['Permission', label(rights.permission_status)],
                              [
                                  'Release / consent',
                                  label(rights.release_consent),
                              ],
                              ['Usage restrictions', rights.usage_restrictions],
                              ['Territories', rights.territories.join(', ')],
                              [
                                  'Embargo until',
                                  rights.embargo_until
                                      ? formatDate(rights.embargo_until)
                                      : null,
                              ],
                          ]
                }
            />

            <FieldTable
                title="Provenance"
                rows={
                    provenance === null
                        ? [['Provenance', null]]
                        : [
                              [
                                  'Source carrier',
                                  label(provenance.source_carrier),
                              ],
                              ['Carrier label', provenance.carrier_label],
                              ['Carrier ID', provenance.carrier_id],
                              [
                                  'Transfer date',
                                  provenance.transfer_date
                                      ? formatDate(provenance.transfer_date)
                                      : null,
                              ],
                              [
                                  'Transfer facility',
                                  provenance.transfer_facility,
                              ],
                              ['Operator', provenance.transfer_operator],
                              ['Deck', provenance.transfer_deck],
                              ['Settings', provenance.transfer_settings],
                              ['Notes', provenance.notes],
                          ]
                }
            />

            <FieldTable
                title="Archival"
                rows={[
                    ['Accession number', archival.accession_number],
                    [
                        'Security classification',
                        label(archival.security_classification),
                    ],
                    ['Retention period', archival.retention_period],
                    ['Archival value', label(archival.archival_value)],
                    [
                        'Disposition date',
                        archival.disposition_date
                            ? formatDate(archival.disposition_date)
                            : null,
                    ],
                    ['Disposition action', label(archival.disposition_action)],
                ]}
            />

            <DataTable
                title="Files and technical metadata"
                columns={[
                    { label: 'Role', className: 'w-24' },
                    { label: 'File' },
                    { label: 'Video' },
                    { label: 'Audio' },
                    { label: 'Duration', className: 'w-24' },
                    { label: 'Size', className: 'w-24' },
                    { label: 'Checksum', className: 'w-28' },
                ]}
                rows={archive.files.map((file) => {
                    const tech = file.technical;

                    return {
                        key: file.id,
                        cells: [
                            formatEnumLabel(file.role),
                            <span key="file" className="grid">
                                {file.original_filename ?? '—'}
                                <span className="font-mono text-xs text-muted-foreground">
                                    {file.sha256_checksum
                                        ? `SHA-256 ${file.sha256_checksum.slice(0, 16)}…`
                                        : ''}
                                </span>
                            </span>,
                            tech
                                ? [
                                      tech.video_codec,
                                      tech.video_profile,
                                      tech.resolution,
                                      tech.frame_rate &&
                                          `${tech.frame_rate} fps`,
                                      tech.scan_type && label(tech.scan_type),
                                      tech.bit_depth && `${tech.bit_depth}-bit`,
                                      tech.chroma_subsampling,
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')
                                : null,
                            tech
                                ? [
                                      tech.audio_codec,
                                      tech.audio_channels &&
                                          `${tech.audio_channels} ch`,
                                      tech.audio_sample_rate &&
                                          `${tech.audio_sample_rate} Hz`,
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')
                                : null,
                            tech?.duration != null
                                ? formatTimestamp(tech.duration)
                                : null,
                            file.file_size !== null
                                ? formatBytes(file.file_size)
                                : null,
                            formatEnumLabel(file.checksum_status),
                        ],
                    };
                })}
                empty="No files."
            />

            <FieldTable
                title="Structure"
                rows={[
                    [
                        'Work',
                        archive.work
                            ? `${archive.work.title}${archive.work.total_parts ? ` (${archive.work.total_parts} parts)` : ''}`
                            : null,
                    ],
                    [
                        'Part',
                        [archive.part_number, archive.part_label]
                            .filter(Boolean)
                            .join(' · '),
                    ],
                    [
                        'Transcripts',
                        archive.transcripts
                            .map(
                                (item) =>
                                    `${languageName(item.language)}${item.is_original ? ' (original)' : ''} – ${formatEnumLabel(item.status)}`,
                            )
                            .join('\n'),
                    ],
                ]}
            />

            <DataTable
                title="Attachments"
                columns={[
                    { label: 'Kind', className: 'w-32' },
                    { label: 'File' },
                    { label: 'Description' },
                    { label: 'Size', className: 'w-24' },
                ]}
                rows={archive.attachments.map((attachment) => ({
                    key: attachment.id,
                    cells: [
                        formatEnumLabel(attachment.kind),
                        attachment.original_filename,
                        attachment.description,
                        formatBytes(attachment.file_size),
                    ],
                }))}
                empty="No attachments."
            />

            <DataTable
                title="Fixity checks"
                columns={[
                    { label: 'Checked', className: 'w-44' },
                    { label: 'File', className: 'w-24' },
                    { label: 'Algorithm', className: 'w-24' },
                    { label: 'Result' },
                    { label: 'Trigger', className: 'w-24' },
                ]}
                rows={archive.fixity.map((check) => ({
                    key: check.id,
                    cells: [
                        formatDateTime(check.checked_at),
                        check.role ? formatEnumLabel(check.role) : null,
                        check.algorithm.toUpperCase(),
                        check.matched === null
                            ? 'Could not run'
                            : check.matched
                              ? 'Matched'
                              : 'Mismatch',
                        formatEnumLabel(check.triggered_by),
                    ],
                }))}
                empty="No fixity checks yet."
            />
        </div>
    );
}
