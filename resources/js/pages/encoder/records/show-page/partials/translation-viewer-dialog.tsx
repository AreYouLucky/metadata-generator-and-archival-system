import { Eye, Pencil } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslationLines } from '@/hooks/use-translations';
import { languageName } from '@/lib/utilities';
import TranslationLine from './translation-line';

type Props = {
    recordId: string;
    language: string;
    canEdit: boolean;
};

/**
 * Read a translation line by line, click a time to play the video there,
 * and (with edit rights) correct any line. The lines load only when the
 * dialog opens.
 */
export default function TranslationViewerDialog({
    recordId,
    language,
    canEdit,
}: Props) {
    const [open, setOpen] = useState(false);
    const lines = useTranslationLines(recordId, language, open);
    const name = languageName(language);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    aria-label={`${canEdit ? 'Read and correct' : 'Read'} the ${name} translation`}
                    title={canEdit ? 'Read and correct' : 'Read'}
                >
                    {canEdit ? <Pencil aria-hidden /> : <Eye aria-hidden />}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{name} translation</DialogTitle>
                    <DialogDescription>
                        Click a time to play the video from there.
                        {canEdit &&
                            ' Double-click a line (or use its pencil) to correct it.'}
                    </DialogDescription>
                </DialogHeader>
                {lines.isPending ? (
                    <div className="flex justify-center p-6">
                        <Spinner />
                    </div>
                ) : lines.isError ? (
                    <p className="text-sm text-destructive">
                        The translation could not be loaded.
                    </p>
                ) : (
                    <ol className="max-h-[60vh] space-y-1.5 overflow-y-auto pr-2">
                        {lines.data.segments.map((segment) => (
                            <TranslationLine
                                key={segment.id}
                                recordId={recordId}
                                language={language}
                                line={segment}
                                canEdit={canEdit}
                            />
                        ))}
                    </ol>
                )}
            </DialogContent>
        </Dialog>
    );
}
