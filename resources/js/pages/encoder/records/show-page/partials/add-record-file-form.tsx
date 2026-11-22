import { Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAddRecordFile } from '@/hooks/use-archive';
import { getErrorMessage } from '@/lib/utilities';
import ArchiveField from './archive-field';
import ArchiveSelect from './archive-select';

const ROLES = [
    { value: 'mezzanine', label: 'Mezzanine (high-quality edit master)' },
    { value: 'access', label: 'Access copy' },
];

/**
 * Add a mezzanine or access file to the record: uploaded in chunks, then
 * checksummed and probed like the master.
 */
export default function AddRecordFileForm({ recordId }: { recordId: string }) {
    const input = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [role, setRole] = useState<'mezzanine' | 'access'>('mezzanine');
    const [sha256, setSha256] = useState('');
    const [progress, setProgress] = useState(0);
    const add = useAddRecordFile(recordId);

    const submit = () => {
        if (!file) {
            return;
        }

        add.mutate(
            {
                file,
                role,
                expectedSha256: sha256.trim(),
                onProgress: setProgress,
            },
            {
                onSuccess: () => {
                    toast.success(
                        'File added; its checksums and technical details follow shortly.',
                    );
                    setFile(null);
                    setSha256('');
                    setProgress(0);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The file could not be added.'),
                    ),
            },
        );
    };

    return (
        <div className="grid gap-3 rounded-lg bg-muted/60 p-3">
            <h4 className="text-sm font-medium">Add a file</h4>
            <input
                ref={input}
                type="file"
                accept="video/*,.mxf"
                aria-label="Video file"
                className="text-xs file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:text-foreground"
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
            <div className="grid gap-3 @md:grid-cols-2">
                <ArchiveField label="Kind" htmlFor="record-file-role">
                    <ArchiveSelect
                        id="record-file-role"
                        value={role}
                        options={ROLES}
                        onChange={(value) =>
                            setRole(value === 'access' ? 'access' : 'mezzanine')
                        }
                    />
                </ArchiveField>
                <ArchiveField
                    label="Expected SHA-256 (optional)"
                    htmlFor="record-file-sha"
                >
                    <Input
                        id="record-file-sha"
                        value={sha256}
                        maxLength={64}
                        className="font-mono text-xs"
                        onChange={(event) => setSha256(event.target.value)}
                    />
                </ArchiveField>
            </div>
            <div className="flex items-center justify-end gap-3">
                {add.isPending && (
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {Math.round(progress * 100)}%
                    </span>
                )}
                <Button
                    size="sm"
                    onClick={submit}
                    disabled={!file || add.isPending}
                >
                    <Upload aria-hidden />
                    Upload
                </Button>
            </div>
        </div>
    );
}
