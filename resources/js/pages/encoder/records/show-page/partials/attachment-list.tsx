import { Download, FileText, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAttachmentMutations } from '@/hooks/use-archive';
import { formatBytes, formatEnumLabel, getErrorMessage } from '@/lib/utilities';
import { show } from '@/routes/records/attachments';
import type { ArchiveAttachment, Option } from '@/types/archive';
import ArchiveSelect from './archive-select';

type Props = {
    recordId: string;
    attachments: ArchiveAttachment[];
    kinds: Option[];
    /** Kind preselected for a new upload (release forms in the Rights tab). */
    defaultKind: string;
    maxMb: number;
    canEdit: boolean;
};

/**
 * Private attachments: release forms, contracts, sidecars, documents. They
 * download only through the record's access check and are never public.
 */
export default function AttachmentList({
    recordId,
    attachments,
    kinds,
    defaultKind,
    maxMb,
    canEdit,
}: Props) {
    const input = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [kind, setKind] = useState(defaultKind);
    const [description, setDescription] = useState('');
    const { upload, remove } = useAttachmentMutations(recordId);
    const onError = (error: unknown) =>
        toast.error(getErrorMessage(error, 'That did not work.'));

    return (
        <div className="grid grid-cols-1 gap-2">
            {attachments.length === 0 ? (
                <p className="text-sm text-muted-foreground">No attachments.</p>
            ) : (
                <ul className="grid grid-cols-1 gap-1.5">
                    {attachments.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex items-center gap-2 rounded-md bg-white/5 px-2.5 py-2 text-sm"
                        >
                            <FileText
                                className="size-4 shrink-0 text-neutral-400"
                                aria-hidden
                            />
                            <div className="min-w-0 flex-1">
                                <p className="wrap-anywhere">
                                    {attachment.original_filename}
                                </p>
                                <p className="text-xs wrap-anywhere text-neutral-500">
                                    {formatEnumLabel(attachment.kind)} ·{' '}
                                    {formatBytes(attachment.file_size)}
                                    {attachment.description &&
                                        ` · ${attachment.description}`}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                asChild
                            >
                                <a
                                    href={show.url({
                                        record: recordId,
                                        attachment: attachment.id,
                                    })}
                                    aria-label={`Download ${attachment.original_filename}`}
                                >
                                    <Download aria-hidden />
                                </a>
                            </Button>
                            {canEdit && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-red-300"
                                    aria-label={`Delete ${attachment.original_filename}`}
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `Delete ${attachment.original_filename}?`,
                                            )
                                        ) {
                                            remove.mutate(attachment.id, {
                                                onError,
                                            });
                                        }
                                    }}
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {canEdit && (
                <div className="grid gap-2 rounded-lg bg-white/5 p-3">
                    <input
                        ref={input}
                        type="file"
                        aria-label="Attachment"
                        accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.xml,.json,.srt,.vtt,.doc,.docx,.xls,.xlsx,.odt,.ods"
                        className="w-full min-w-0 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-xs file:text-foreground"
                        onChange={(event) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
                    />
                    <div className="flex flex-wrap gap-2">
                        <div className="w-40 max-w-full">
                            <ArchiveSelect
                                id={`attachment-kind-${defaultKind}`}
                                value={kind}
                                options={kinds}
                                onChange={(value) =>
                                    setKind(value ?? defaultKind)
                                }
                            />
                        </div>
                        <Input
                            value={description}
                            maxLength={1000}
                            placeholder="Description (optional)"
                            aria-label="Attachment description"
                            onChange={(event) =>
                                setDescription(event.target.value)
                            }
                            className="min-w-32 flex-1"
                        />
                        <Button
                            size="icon"
                            variant="secondary"
                            aria-label="Upload attachment"
                            disabled={!file || upload.isPending}
                            onClick={() =>
                                file &&
                                upload.mutate(
                                    { file, kind, description },
                                    {
                                        onSuccess: () => {
                                            toast.success('Attachment added.');
                                            setFile(null);
                                            setDescription('');

                                            if (input.current) {
                                                input.current.value = '';
                                            }
                                        },
                                        onError,
                                    },
                                )
                            }
                        >
                            <Upload aria-hidden />
                        </Button>
                    </div>
                    <p className="text-xs wrap-anywhere text-neutral-500">
                        PDF, images, documents, subtitles or sidecars, up to{' '}
                        {maxMb} MB. Stored privately.
                    </p>
                </div>
            )}
        </div>
    );
}
