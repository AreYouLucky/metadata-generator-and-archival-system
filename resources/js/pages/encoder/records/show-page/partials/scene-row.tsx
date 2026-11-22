import { Check, Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDeleteScene, useUpdateScene } from '@/hooks/use-visuals';
import { getErrorMessage } from '@/lib/utilities';
import { useWorkspaceStore } from '@/stores/workspace-store';
import type { Scene } from '@/types/visuals';

type Props = {
    recordId: string;
    scene: Scene;
    label: string;
    canEdit: boolean;
};

/** A named scene: click to jump there; rename or remove it. */
export default function SceneRow({ recordId, scene, label, canEdit }: Props) {
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(scene.label ?? '');
    const seek = useWorkspaceStore((state) => state.seek);
    const update = useUpdateScene(recordId);
    const remove = useDeleteScene(recordId);

    const save = (event: FormEvent) => {
        event.preventDefault();
        update.mutate(
            {
                sceneId: scene.id,
                start: scene.start,
                end: scene.end,
                label: name.trim(),
                description: scene.description ?? '',
            },
            {
                onSuccess: () => setEditing(false),
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The scene could not be saved.'),
                    ),
            },
        );
    };

    if (editing) {
        return (
            <form onSubmit={save} className="flex items-center gap-1">
                <Input
                    value={name}
                    maxLength={120}
                    aria-label="Scene name"
                    className="h-8"
                    onChange={(event) => setName(event.target.value)}
                    autoFocus
                />
                <Button
                    type="submit"
                    size="icon"
                    variant="ghost"
                    className="size-8"
                    disabled={name.trim() === '' || update.isPending}
                    aria-label="Save the name"
                >
                    <Check aria-hidden />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-8"
                    onClick={() => setEditing(false)}
                    aria-label="Cancel"
                >
                    <X aria-hidden />
                </Button>
            </form>
        );
    }

    return (
        <div className="group flex items-center gap-2 rounded-sm px-2 py-1 text-sm hover:bg-white/5">
            <button
                type="button"
                onClick={() => seek(scene.start)}
                className="grid min-w-0 flex-1 text-left focus-visible:underline focus-visible:outline-none"
            >
                <span className="truncate">{scene.label}</span>
                <span className="font-mono text-[11px] text-neutral-500 tabular-nums">
                    {label}
                </span>
            </button>
            {canEdit && (
                <div className="flex opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        onClick={() => {
                            setName(scene.label ?? '');
                            setEditing(true);
                        }}
                        aria-label={`Rename ${scene.label}`}
                    >
                        <Pencil aria-hidden />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-7"
                        disabled={remove.isPending}
                        onClick={() =>
                            remove.mutate(scene.id, {
                                onSuccess: () =>
                                    toast.success(
                                        `Scene “${scene.label}” removed.`,
                                    ),
                                onError: (error) =>
                                    toast.error(
                                        getErrorMessage(
                                            error,
                                            'The scene could not be removed.',
                                        ),
                                    ),
                            })
                        }
                        aria-label={`Remove ${scene.label}`}
                    >
                        <Trash2 aria-hidden />
                    </Button>
                </div>
            )}
        </div>
    );
}
