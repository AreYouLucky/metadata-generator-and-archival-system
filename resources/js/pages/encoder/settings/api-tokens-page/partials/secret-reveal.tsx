import { Check, Copy } from 'lucide-react';
import { useClipboard } from '@/hooks/use-clipboard';
import { Button } from '@/components/ui/button';

type Props = {
    label: string;
    value: string;
};

/**
 * A token or secret shown once, right after it was made, with a copy button.
 */
export default function SecretReveal({ label, value }: Props) {
    const [copied, copy] = useClipboard();

    return (
        <div className="grid gap-1.5 rounded-lg border border-tertiary bg-tertiary/15 p-3">
            <p className="text-xs font-medium">
                {label} — copy it now, it won’t be shown again.
            </p>
            <div className="flex items-center gap-2">
                <code className="min-w-0 flex-1 truncate rounded bg-card px-2 py-1 font-mono text-xs">
                    {value}
                </code>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-8"
                    aria-label={`Copy ${label.toLowerCase()}`}
                    onClick={() => void copy(value)}
                >
                    {copied === value ? (
                        <Check aria-hidden />
                    ) : (
                        <Copy aria-hidden />
                    )}
                </Button>
            </div>
        </div>
    );
}
