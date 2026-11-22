import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type Props = {
    label: string;
    htmlFor?: string;
    hint?: ReactNode;
    error?: string;
    className?: string;
    children: ReactNode;
};

/** One labelled field of an Archive sub-tab, with an optional hint and error. */
export default function ArchiveField({
    label,
    htmlFor,
    hint,
    error,
    className,
    children,
}: Props) {
    return (
        <div className={className ?? 'grid min-w-0 gap-1.5'}>
            <Label htmlFor={htmlFor} className="text-xs">
                {label}
            </Label>
            {children}
            {hint && <p className="text-xs text-neutral-500">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
