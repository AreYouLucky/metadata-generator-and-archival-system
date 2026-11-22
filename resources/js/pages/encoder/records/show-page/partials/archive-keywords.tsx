import { Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useKeywordMutations } from '@/hooks/use-archive';
import { getErrorMessage } from '@/lib/utilities';
import type { ArchiveKeyword } from '@/types/archive';
import SuggestionChip from './suggestion-chip';

type Props = {
    recordId: string;
    keywords: ArchiveKeyword[];
    canEdit: boolean;
};

/**
 * Archive keywords (the AI's become suggestions here). Only confirmed
 * keywords are ever public.
 */
export default function ArchiveKeywords({
    recordId,
    keywords,
    canEdit,
}: Props) {
    const { add, update, remove } = useKeywordMutations(recordId);
    const [keyword, setKeyword] = useState('');
    const pending = keywords.filter((item) => !item.is_confirmed);
    const onError = (error: unknown) => toast.error(getErrorMessage(error));

    return (
        <section className="grid gap-2 border-t border-border pt-4">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-sm font-medium">
                    Keywords
                    {pending.length > 0 && (
                        <span className="ml-2 text-xs font-normal text-brand">
                            {pending.length} to review
                        </span>
                    )}
                </h3>
            </div>
            {keywords.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No keywords yet.
                </p>
            ) : (
                <ul className="flex flex-wrap gap-1.5">
                    {keywords.map((item) => (
                        <SuggestionChip
                            key={item.id}
                            label={item.keyword}
                            confirmed={item.is_confirmed}
                            source={item.source}
                            disabled={!canEdit}
                            onConfirm={() =>
                                update.mutate(
                                    { id: item.id, is_confirmed: true },
                                    { onError },
                                )
                            }
                            onRemove={() => remove.mutate(item.id, { onError })}
                        />
                    ))}
                </ul>
            )}
            {canEdit && (
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        add.mutate(keyword.trim(), {
                            onSuccess: () => setKeyword(''),
                            onError,
                        });
                    }}
                >
                    <Input
                        value={keyword}
                        maxLength={100}
                        placeholder="Add a keyword"
                        aria-label="New keyword"
                        onChange={(event) => setKeyword(event.target.value)}
                        className="min-w-0 flex-1"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        variant="secondary"
                        disabled={keyword.trim() === '' || add.isPending}
                        aria-label="Add keyword"
                    >
                        <Plus aria-hidden />
                    </Button>
                </form>
            )}
        </section>
    );
}
