import { Link } from '@inertiajs/react';
import { formatEnumLabel, formatNumber, languageName } from '@/lib/utilities';
import { show } from '@/routes/records';
import type { Archive, ArchiveOptions } from '@/types/archive';
import AttachmentList from './attachment-list';
import WorkPicker from './work-picker';

type Props = {
    recordId: string;
    archive: Archive;
    options: ArchiveOptions;
    canEdit: boolean;
};

/**
 * How the pieces relate: the stored files by role, the work this record is
 * a part of, attachments, transcripts by language, and shot segments.
 */
export default function ArchiveStructure({
    recordId,
    archive,
    options,
    canEdit,
}: Props) {
    return (
        <div className="grid grid-cols-1 gap-5">
            <section className="grid grid-cols-1 gap-2">
                <h3 className="text-sm font-medium">Files</h3>
                <ul className="grid grid-cols-1 gap-1 text-sm">
                    {archive.files.map((file) => (
                        <li
                            key={file.id}
                            className="flex justify-between gap-2"
                        >
                            <span className="shrink-0">
                                {formatEnumLabel(file.role)}
                            </span>
                            <span className="min-w-0 text-right wrap-anywhere text-neutral-500">
                                {file.original_filename}
                            </span>
                        </li>
                    ))}
                </ul>
            </section>

            <section className="grid grid-cols-1 gap-2 border-t border-white/10 pt-4">
                <h3 className="text-sm font-medium">Work (reels and parts)</h3>
                <WorkPicker
                    recordId={recordId}
                    archive={archive}
                    options={options}
                    canEdit={canEdit}
                />
                {archive.work && archive.work.parts.length > 1 && (
                    <ol className="grid grid-cols-1 gap-1 text-sm">
                        {archive.work.parts.map((part) => (
                            <li key={part.id}>
                                <Link
                                    href={show(part.id)}
                                    className="hover:underline"
                                    aria-current={
                                        part.id === recordId
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {part.part_label ??
                                        `Part ${part.part_number ?? '?'}`}
                                    : {part.title}
                                </Link>
                            </li>
                        ))}
                    </ol>
                )}
            </section>

            <section className="grid grid-cols-1 gap-2 border-t border-white/10 pt-4">
                <h3 className="text-sm font-medium">Attachments</h3>
                <AttachmentList
                    recordId={recordId}
                    attachments={archive.attachments}
                    kinds={options.attachmentKinds}
                    defaultKind="sidecar"
                    maxMb={options.maxAttachmentMb}
                    canEdit={canEdit}
                />
            </section>

            <section className="grid grid-cols-1 gap-2 border-t border-white/10 pt-4">
                <h3 className="text-sm font-medium">Transcripts</h3>
                <ul className="grid grid-cols-1 gap-1 text-sm">
                    {archive.transcripts.map((transcript) => (
                        <li
                            key={transcript.language}
                            className="flex flex-wrap justify-between gap-x-2"
                        >
                            <span>{languageName(transcript.language)}</span>
                            <span className="text-right text-neutral-500">
                                {transcript.is_original
                                    ? 'Original'
                                    : `Translation · ${formatEnumLabel(transcript.status)}`}
                            </span>
                        </li>
                    ))}
                </ul>
            </section>

            <section className="grid grid-cols-1 gap-2 border-t border-white/10 pt-4">
                <h3 className="text-sm font-medium">Shot and scene segments</h3>
                <p className="text-sm text-muted-foreground">
                    {archive.segments.shot_count > 0
                        ? `${formatNumber(archive.segments.shot_count)} shots detected, with timecode in and out.`
                        : 'No shots yet: they come from visual analysis.'}{' '}
                    Group them into named scenes in the Visuals tab.
                </p>
                {archive.segments.scenes.length > 0 && (
                    <ul className="grid grid-cols-1 gap-1 text-sm">
                        {archive.segments.scenes.map((scene) => (
                            <li
                                key={scene.id}
                                className="flex justify-between gap-2"
                            >
                                <span className="min-w-0 wrap-anywhere">
                                    {scene.label}
                                </span>
                                <span className="shrink-0 font-mono text-xs text-neutral-500 tabular-nums">
                                    {scene.tc_in} – {scene.tc_out}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
