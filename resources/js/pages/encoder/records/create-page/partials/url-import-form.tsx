import { Link2, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    disabled: boolean;
    onAdd: (url: string) => void;
};

/**
 * Add a web address (YouTube, Vimeo, Facebook and the other sites yt-dlp
 * supports); the server downloads it. The rights notice is required by
 * PROJECT_SPEC §13 (Phase 6). The server checks the address again.
 */
export default function UrlImportForm({ disabled, onAdd }: Props) {
    const [url, setUrl] = useState('');
    const [error, setError] = useState<string | null>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const value = url.trim();

        if (!/^https?:\/\/[^\s/]+\.[^\s]+$/i.test(value)) {
            setError('Enter a web address starting with http:// or https://');

            return;
        }

        onAdd(value);
        setUrl('');
        setError(null);
    };

    return (
        <form onSubmit={submit} className="grid gap-1.5">
            <label
                htmlFor="import-url"
                className="flex items-center gap-1.5 text-xs font-medium text-foreground/80"
            >
                <Link2 className="size-3.5" aria-hidden />
                Import from a web address
            </label>
            <div className="flex gap-1.5">
                <Input
                    id="import-url"
                    type="url"
                    inputMode="url"
                    value={url}
                    disabled={disabled}
                    placeholder="https://www.youtube.com/watch?v=…"
                    className="h-8 text-xs"
                    onChange={(event) => setUrl(event.target.value)}
                />
                <Button
                    type="submit"
                    size="sm"
                    variant="secondary"
                    className="h-8"
                    disabled={disabled || url.trim() === ''}
                >
                    Add
                </Button>
            </div>
            <InputError message={error ?? undefined} />
            <p className="flex gap-1.5 text-[11px] text-amber-700/90">
                <ShieldAlert className="mt-px size-3.5 shrink-0" aria-hidden />
                Only import videos you have the rights to archive.
            </p>
        </form>
    );
}
