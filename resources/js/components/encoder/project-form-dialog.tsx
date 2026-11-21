import { isAxiosError } from 'axios';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import LocationPicker from '@/components/all/location-picker';
import AgencySelect from '@/components/encoder/agency-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { EMPTY_LOCATION } from '@/hooks/use-locations';
import { getErrorMessage } from '@/lib/utilities';
import type { ProjectFormValues } from '@/types/projects';
import ProjectThumbnailField from './partials/project-thumbnail-field';

const EMPTY: ProjectFormValues = {
    ...EMPTY_LOCATION,
    title: '',
    date: '',
    end_date: '',
    description: '',
    location: '',
    agency_id: null,
    thumbnail: null,
    remove_thumbnail: false,
};

type Props = {
    trigger: ReactNode;
    heading: string;
    explanation: string;
    titlePlaceholder: string;
    submitLabel: string;
    pending: boolean;
    /** Projects also have a thumbnail; series share the other fields. */
    variant?: 'project' | 'series';
    /** Saved values when editing; the form resets to them on every open. */
    initial?: Partial<ProjectFormValues>;
    /** The saved thumbnail, when editing a project. */
    currentThumbnailUrl?: string | null;
    /** Resolves when saved; rejects with the request error. */
    onSubmit: (values: ProjectFormValues) => Promise<unknown>;
};

/**
 * Create or edit a project (event) or one of its series: both have a title,
 * first and last day, agency, description, venue and place; projects also
 * have a thumbnail. Used on the Projects pages and inline on Import media.
 */
export default function ProjectFormDialog({
    trigger,
    heading,
    explanation,
    titlePlaceholder,
    submitLabel,
    pending,
    variant = 'project',
    initial,
    currentThumbnailUrl = null,
    onSubmit,
}: Props) {
    const [open, setOpen] = useState(false);
    const [values, setValues] = useState<ProjectFormValues>(EMPTY);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const set = (changes: Partial<ProjectFormValues>) =>
        setValues((current) => ({ ...current, ...changes }));

    const changeOpen = (next: boolean) => {
        if (next) {
            setValues({ ...EMPTY, ...initial });
            setErrors({});
        }

        setOpen(next);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        event.stopPropagation();
        setErrors({});
        onSubmit(values)
            .then(() => {
                setValues(EMPTY);
                setOpen(false);
            })
            .catch((error: unknown) => {
                if (
                    isAxiosError<{ errors?: Record<string, string[]> }>(
                        error,
                    ) &&
                    error.response?.data.errors
                ) {
                    setErrors(
                        Object.fromEntries(
                            Object.entries(error.response.data.errors).map(
                                ([key, messages]) => [key, messages[0]],
                            ),
                        ),
                    );
                } else {
                    toast.error(getErrorMessage(error));
                }
            });
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{heading}</DialogTitle>
                        <DialogDescription>{explanation}</DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="project-title">Name</Label>
                        <Input
                            id="project-title"
                            required
                            maxLength={255}
                            value={values.title}
                            placeholder={titlePlaceholder}
                            onChange={(event) =>
                                set({ title: event.target.value })
                            }
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="project-date">First day</Label>
                            <Input
                                id="project-date"
                                type="date"
                                value={values.date}
                                onChange={(event) =>
                                    set({ date: event.target.value })
                                }
                            />
                            <InputError message={errors.date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="project-end-date">
                                Last day (if several)
                            </Label>
                            <Input
                                id="project-end-date"
                                type="date"
                                min={values.date || undefined}
                                value={values.end_date}
                                onChange={(event) =>
                                    set({ end_date: event.target.value })
                                }
                            />
                            <InputError message={errors.end_date} />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <AgencySelect
                                idPrefix={variant}
                                agencyId={values.agency_id}
                                onChange={(agencyId) =>
                                    set({ agency_id: agencyId })
                                }
                            />
                            <InputError message={errors.agency_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="project-location">Venue</Label>
                            <Input
                                id="project-location"
                                maxLength={255}
                                value={values.location}
                                placeholder="e.g. Provincial Capitol grounds"
                                onChange={(event) =>
                                    set({ location: event.target.value })
                                }
                            />
                            <InputError message={errors.location} />
                        </div>
                    </div>
                    <LocationPicker
                        idPrefix="project"
                        value={values}
                        errors={errors}
                        onChange={(location) => set(location)}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="project-description">
                            Description (optional)
                        </Label>
                        <Textarea
                            id="project-description"
                            rows={3}
                            maxLength={5000}
                            value={values.description}
                            onChange={(event) =>
                                set({ description: event.target.value })
                            }
                        />
                        <InputError message={errors.description} />
                    </div>
                    {variant === 'project' && (
                        <ProjectThumbnailField
                            currentUrl={currentThumbnailUrl}
                            file={values.thumbnail}
                            removed={values.remove_thumbnail}
                            error={errors.thumbnail}
                            onChange={(changes) => set(changes)}
                        />
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={pending}>
                            {pending && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
