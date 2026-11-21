import { ImageIcon, Trash2, Upload } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Props = {
    /** The saved cover, when editing. */
    currentUrl: string | null;
    file: File | null;
    removed: boolean;
    error?: string;
    onChange: (changes: {
        thumbnail: File | null;
        remove_thumbnail: boolean;
    }) => void;
};

/**
 * A project's cover image: preview, choose a new one, or remove it. The
 * server checks type and size (JPG, PNG or WebP, up to 5 MB).
 */
export default function ProjectThumbnailField({
    currentUrl,
    file,
    removed,
    error,
    onChange,
}: Props) {
    const input = useRef<HTMLInputElement>(null);
    const localUrl = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(
        () => () => {
            if (localUrl) {
                URL.revokeObjectURL(localUrl);
            }
        },
        [localUrl],
    );

    const preview = localUrl ?? (removed ? null : currentUrl);

    const clear = () => {
        if (input.current) {
            input.current.value = '';
        }

        onChange({ thumbnail: null, remove_thumbnail: currentUrl !== null });
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="project-thumbnail">Thumbnail (optional)</Label>
            <div className="flex items-center gap-4">
                <div className="flex aspect-video w-36 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
                    {preview ? (
                        <img
                            src={preview}
                            alt="Thumbnail preview"
                            className="size-full object-cover"
                        />
                    ) : (
                        <ImageIcon
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                    )}
                </div>
                <div className="flex flex-col items-start gap-2">
                    <input
                        ref={input}
                        id="project-thumbnail"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        onChange={(event) =>
                            onChange({
                                thumbnail: event.target.files?.[0] ?? null,
                                remove_thumbnail: false,
                            })
                        }
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => input.current?.click()}
                    >
                        <Upload aria-hidden />
                        {preview ? 'Replace image' : 'Choose image'}
                    </Button>
                    {preview && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={clear}
                        >
                            <Trash2 aria-hidden />
                            Remove
                        </Button>
                    )}
                    <p className="text-xs text-muted-foreground">
                        JPG, PNG or WebP, up to 5 MB.
                    </p>
                </div>
            </div>
            <InputError message={error} />
        </div>
    );
}
