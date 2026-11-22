import { Group } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCreateScene } from '@/hooks/use-visuals';
import { getErrorMessage } from '@/lib/utilities';

type Props = {
    recordId: string;
    start: number;
    end: number;
    shotCount: number;
    onDone: () => void;
};

/** Name the selected run of shots as one scene. */
export default function SceneGroupForm({
    recordId,
    start,
    end,
    shotCount,
    onDone,
}: Props) {
    const [label, setLabel] = useState('');
    const [description, setDescription] = useState('');
    const create = useCreateScene(recordId);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        create.mutate(
            {
                start,
                end,
                label: label.trim(),
                description: description.trim(),
            },
            {
                onSuccess: (scene) => {
                    toast.success(`Scene “${scene.label}” added.`);
                    onDone();
                },
                onError: (error) =>
                    toast.error(
                        getErrorMessage(error, 'The scene could not be saved.'),
                    ),
            },
        );
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-2 rounded-md bg-muted/60 p-3"
        >
            <div className="grid gap-1.5">
                <Label htmlFor="scene-label">Scene name</Label>
                <Input
                    id="scene-label"
                    value={label}
                    maxLength={120}
                    placeholder="e.g. Opening remarks"
                    onChange={(event) => setLabel(event.target.value)}
                    autoFocus
                />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="scene-description">
                    Description (optional)
                </Label>
                <Textarea
                    id="scene-description"
                    rows={2}
                    maxLength={2000}
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                />
            </div>
            <div className="flex gap-2">
                <Button
                    type="submit"
                    size="sm"
                    disabled={label.trim() === '' || create.isPending}
                >
                    <Group aria-hidden />
                    Group {shotCount} {shotCount === 1 ? 'shot' : 'shots'}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onDone}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
