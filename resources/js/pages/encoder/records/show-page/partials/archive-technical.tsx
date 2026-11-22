import type { Archive } from '@/types/archive';
import AddRecordFileForm from './add-record-file-form';
import TechnicalFileCard from './technical-file-card';

type Props = {
    recordId: string;
    archive: Archive;
    canEdit: boolean;
};

/**
 * How each stored file is encoded (read-only, from probing) with its
 * checksums: the master, the access copy made for browsers, and any added
 * mezzanine or access files.
 */
export default function ArchiveTechnical({
    recordId,
    archive,
    canEdit,
}: Props) {
    return (
        <div className="grid gap-3">
            {archive.files.map((file) => (
                <TechnicalFileCard key={file.id} file={file} />
            ))}
            {canEdit && <AddRecordFileForm recordId={recordId} />}
        </div>
    );
}
