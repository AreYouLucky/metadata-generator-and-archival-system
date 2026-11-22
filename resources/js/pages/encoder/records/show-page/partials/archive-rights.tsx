import EmbargoBadge from '@/components/encoder/embargo-badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useArchiveForm } from '@/hooks/use-archive-form';
import { useSaveRights } from '@/hooks/use-archive';
import type {
    Archive,
    ArchiveOptions,
    ArchiveRights as Rights,
} from '@/types/archive';
import ArchiveField from './archive-field';
import ArchiveSaveBar from './archive-save-bar';
import ArchiveSelect from './archive-select';
import AttachmentList from './attachment-list';

type Props = {
    recordId: string;
    archive: Archive;
    options: ArchiveOptions;
    canEdit: boolean;
};

/** Public by default (owner's choice): set Internal or Restricted to keep a video off the public site. */
const EMPTY: Rights = {
    copyright_holder: null,
    copyright_year: null,
    rights_statement: null,
    license: null,
    license_url: null,
    access_level: 'public',
    usage_restrictions: null,
    territories: [],
    embargo_until: null,
    permission_status: 'approved',
    release_consent: 'not_available',
};

/**
 * Who owns the video and how it may be used. Access level, permission and
 * embargo decide (with approval and classification) whether it can ever be
 * public. Release forms are kept here, privately.
 */
export default function ArchiveRights({
    recordId,
    archive,
    options,
    canEdit,
}: Props) {
    const { values, change, dirty, errors, feedback } = useArchiveForm(
        archive.rights ?? EMPTY,
    );
    const save = useSaveRights(recordId);
    const text = (value: string) => (value.trim() === '' ? null : value);

    return (
        <div className="grid gap-4">
            {archive.embargoed && archive.rights?.embargo_until && (
                <EmbargoBadge until={archive.rights.embargo_until} />
            )}
            <div className="grid gap-4 @md:grid-cols-[1fr_7rem]">
                <ArchiveField
                    label="Copyright holder"
                    htmlFor="rights-holder"
                    error={errors.copyright_holder}
                >
                    <Input
                        id="rights-holder"
                        value={values.copyright_holder ?? ''}
                        maxLength={250}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                copyright_holder: text(event.target.value),
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Year"
                    htmlFor="rights-year"
                    error={errors.copyright_year}
                >
                    <Input
                        id="rights-year"
                        type="number"
                        value={values.copyright_year ?? ''}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                copyright_year:
                                    event.target.value === ''
                                        ? null
                                        : Number(event.target.value),
                            })
                        }
                    />
                </ArchiveField>
            </div>
            <ArchiveField
                label="Rights statement"
                htmlFor="rights-statement"
                error={errors.rights_statement}
            >
                <Textarea
                    id="rights-statement"
                    rows={2}
                    value={values.rights_statement ?? ''}
                    maxLength={5000}
                    disabled={!canEdit}
                    onChange={(event) =>
                        change({ rights_statement: text(event.target.value) })
                    }
                />
            </ArchiveField>
            <div className="grid gap-4 @md:grid-cols-2">
                <ArchiveField
                    label="Licence"
                    htmlFor="rights-license"
                    error={errors.license}
                >
                    <Input
                        id="rights-license"
                        value={values.license ?? ''}
                        maxLength={250}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({ license: text(event.target.value) })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Licence link"
                    htmlFor="rights-license-url"
                    error={errors.license_url}
                >
                    <Input
                        id="rights-license-url"
                        type="url"
                        value={values.license_url ?? ''}
                        maxLength={250}
                        placeholder="https://"
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({ license_url: text(event.target.value) })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Access level"
                    htmlFor="rights-access"
                    error={errors.access_level}
                >
                    <ArchiveSelect
                        id="rights-access"
                        value={values.access_level}
                        options={options.accessLevels}
                        disabled={!canEdit}
                        onChange={(value) =>
                            change({ access_level: value ?? 'public' })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Permission"
                    htmlFor="rights-permission"
                    error={errors.permission_status}
                >
                    <ArchiveSelect
                        id="rights-permission"
                        value={values.permission_status}
                        options={options.permissionStatuses}
                        disabled={!canEdit}
                        onChange={(value) =>
                            change({ permission_status: value ?? 'approved' })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Release / consent"
                    htmlFor="rights-release"
                    error={errors.release_consent}
                >
                    <ArchiveSelect
                        id="rights-release"
                        value={values.release_consent}
                        options={options.releaseConsents}
                        disabled={!canEdit}
                        onChange={(value) =>
                            change({
                                release_consent: value ?? 'not_available',
                            })
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Embargo until"
                    htmlFor="rights-embargo"
                    error={errors.embargo_until}
                >
                    <Input
                        id="rights-embargo"
                        type="date"
                        value={values.embargo_until ?? ''}
                        disabled={!canEdit}
                        onChange={(event) =>
                            change({
                                embargo_until: event.target.value || null,
                            })
                        }
                    />
                </ArchiveField>
            </div>
            <ArchiveField
                label="Usage restrictions"
                htmlFor="rights-restrictions"
                error={errors.usage_restrictions}
            >
                <Textarea
                    id="rights-restrictions"
                    rows={2}
                    value={values.usage_restrictions ?? ''}
                    maxLength={5000}
                    disabled={!canEdit}
                    onChange={(event) =>
                        change({ usage_restrictions: text(event.target.value) })
                    }
                />
            </ArchiveField>
            <ArchiveField
                label="Territories"
                htmlFor="rights-territories"
                hint="Two-letter country codes, separated by commas, e.g. PH, US"
                error={errors.territories}
            >
                <Input
                    id="rights-territories"
                    value={values.territories.join(', ')}
                    disabled={!canEdit}
                    onChange={(event) =>
                        change({
                            territories: event.target.value
                                .split(',')
                                .map((code) => code.trim().toUpperCase())
                                .filter((code) => code !== ''),
                        })
                    }
                />
            </ArchiveField>
            {canEdit && (
                <ArchiveSaveBar
                    dirty={dirty || archive.rights === null}
                    pending={save.isPending}
                    onSave={() =>
                        save.mutate(values, feedback('Rights saved.'))
                    }
                />
            )}

            <section className="grid gap-2 border-t border-white/10 pt-4">
                <h3 className="text-sm font-medium">Release forms</h3>
                <AttachmentList
                    recordId={recordId}
                    attachments={archive.attachments.filter(
                        (attachment) => attachment.kind === 'release_form',
                    )}
                    kinds={options.attachmentKinds}
                    defaultKind="release_form"
                    maxMb={options.maxAttachmentMb}
                    canEdit={canEdit}
                />
            </section>
        </div>
    );
}
