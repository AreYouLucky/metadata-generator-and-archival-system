import { Upload } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { usePublishToYouTube } from '@/hooks/use-youtube';
import { getErrorMessage } from '@/lib/utilities';
import type {
    Publishing,
    PublishInput,
    YouTubePrivacy,
} from '@/types/publishing';
import ChipInput from './chip-input';

type Props = {
    recordId: string;
    publishing: Publishing;
    /** Earlier uploads exist: publishing again makes a second copy. */
    alreadyOnYouTube: boolean;
    /** Another publication is still on its way. */
    busy: boolean;
};

/** How YouTube counts tags toward its limit (quotes around tags with spaces). */
const tagsLength = (tags: string[]) =>
    tags.reduce(
        (total, tag) => total + tag.length + (tag.includes(' ') ? 2 : 0),
        0,
    ) + Math.max(0, tags.length - 1);

/**
 * What goes to YouTube: title, description (chapters and hashtags already
 * added from the metadata), tags, category, privacy (private by default),
 * the made-for-kids declaration and the custom thumbnail. Starts from the
 * record; nothing typed here changes the record itself.
 */
export default function PublishForm({
    recordId,
    publishing,
    alreadyOnYouTube,
    busy,
}: Props) {
    const { defaults, limits } = publishing;
    const [form, setForm] = useState<PublishInput>({
        privacy_status: 'private',
        title: defaults.title,
        description: defaults.description,
        tags: defaults.tags,
        category_id: defaults.category_id,
        made_for_kids: false,
        with_thumbnail: publishing.thumbnail_usable,
    });
    const publish = usePublishToYouTube(recordId);
    const tagChars = tagsLength(form.tags);
    const invalid =
        form.title.trim() === '' ||
        /[<>]/.test(form.title + form.description) ||
        tagChars > limits.tags;
    const change = (changes: Partial<PublishInput>) =>
        setForm((current) => ({ ...current, ...changes }));

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();

                if (
                    alreadyOnYouTube &&
                    !window.confirm(
                        'This record is already on YouTube. Upload a second copy?',
                    )
                ) {
                    return;
                }

                publish.mutate(form, {
                    onSuccess: () =>
                        toast.success(
                            'Publishing to YouTube. You can keep working; progress shows here.',
                        ),
                });
            }}
        >
            <div className="grid gap-1.5">
                <div className="flex items-baseline justify-between">
                    <Label htmlFor="youtube-title">Title</Label>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {form.title.length} / {limits.title}
                    </span>
                </div>
                <Input
                    id="youtube-title"
                    value={form.title}
                    maxLength={limits.title}
                    required
                    onChange={(event) => change({ title: event.target.value })}
                />
            </div>

            <div className="grid gap-1.5">
                <div className="flex items-baseline justify-between">
                    <Label htmlFor="youtube-description">Description</Label>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {form.description.length} / {limits.description}
                    </span>
                </div>
                <Textarea
                    id="youtube-description"
                    value={form.description}
                    maxLength={limits.description}
                    rows={8}
                    className="font-mono text-xs"
                    onChange={(event) =>
                        change({ description: event.target.value })
                    }
                />
                <p className="text-xs text-muted-foreground">
                    YouTube does not allow &lt; or &gt;. Chapter lines (00:00
                    Title) become chapters on YouTube.
                </p>
            </div>

            <div className="grid gap-1">
                <ChipInput
                    id="youtube-tags"
                    label="Tags"
                    values={form.tags}
                    max={100}
                    disabled={false}
                    onChange={(tags) =>
                        change({
                            tags: tags.map((tag) => tag.replace(/[<>,]/g, '')),
                        })
                    }
                />
                <p
                    className={
                        tagChars > limits.tags
                            ? 'text-xs text-destructive'
                            : 'text-xs text-muted-foreground'
                    }
                >
                    {tagChars} / {limits.tags} characters
                </p>
            </div>

            <div className="grid gap-3 @md:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="youtube-privacy">Privacy</Label>
                    <Select
                        value={form.privacy_status}
                        onValueChange={(value) =>
                            change({ privacy_status: value as YouTubePrivacy })
                        }
                    >
                        <SelectTrigger id="youtube-privacy">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {publishing.privacy_options.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="youtube-category">Category</Label>
                    <Select
                        value={form.category_id}
                        onValueChange={(value) =>
                            change({ category_id: value })
                        }
                    >
                        <SelectTrigger id="youtube-category">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {publishing.categories.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="grid gap-2">
                <div className="flex items-start gap-2">
                    <Checkbox
                        id="youtube-thumbnail"
                        checked={form.with_thumbnail}
                        disabled={!publishing.thumbnail_usable}
                        onCheckedChange={(checked) =>
                            change({ with_thumbnail: checked === true })
                        }
                    />
                    <Label
                        htmlFor="youtube-thumbnail"
                        className="leading-snug font-normal"
                    >
                        Use the record’s thumbnail
                        {!publishing.thumbnail_usable && (
                            <span className="block text-xs text-muted-foreground">
                                Choose a thumbnail in Visuals first (JPEG or
                                PNG, 2 MB at most).
                            </span>
                        )}
                    </Label>
                </div>
                <div className="flex items-start gap-2">
                    <Checkbox
                        id="youtube-kids"
                        checked={form.made_for_kids}
                        onCheckedChange={(checked) =>
                            change({ made_for_kids: checked === true })
                        }
                    />
                    <Label
                        htmlFor="youtube-kids"
                        className="leading-snug font-normal"
                    >
                        Made for kids (required by YouTube; turns off comments
                        and personalised ads)
                    </Label>
                </div>
            </div>

            {publish.error && (
                <InputError
                    message={getErrorMessage(
                        publish.error,
                        'The video could not be sent to YouTube.',
                    )}
                />
            )}

            <div className="flex justify-end">
                <Button
                    type="submit"
                    size="sm"
                    disabled={publish.isPending || busy || invalid}
                >
                    {publish.isPending ? <Spinner /> : <Upload aria-hidden />}
                    Publish to YouTube
                </Button>
            </div>
        </form>
    );
}
