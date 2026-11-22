<?php

namespace App\Http\Requests\Encoder;

use App\Enums\PublicationStatus;
use App\Enums\YouTubePrivacy;
use App\Models\Record;
use App\Models\User;
use App\Services\YouTube\YouTubeDraft;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /records/{record}/youtube: publish to the user's own connected
 * channel. Needs the `publish` ability (the record may leave the archive),
 * YouTube's limits on title, description and tags, a connected channel, a
 * video file, and no other publication of the record still on its way.
 */
class PublishToYouTubeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record !== null && ($this->user()?->can('publish', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'privacy_status' => ['required', Rule::enum(YouTubePrivacy::class)],
            'title' => ['required', 'string', 'max:'.YouTubeDraft::TITLE_MAX, 'not_regex:/[<>]/'],
            'description' => ['nullable', 'string', 'max:'.YouTubeDraft::DESCRIPTION_MAX, 'not_regex:/[<>]/'],
            'tags' => ['nullable', 'array', 'max:100'],
            'tags.*' => ['required', 'string', 'max:100', 'distinct:ignore_case', 'not_regex:/[<>,]/'],
            'category_id' => ['required', 'string', Rule::in(YouTubeDraft::categoryIds())],
            'made_for_kids' => ['required', 'boolean'],
            'with_thumbnail' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.not_regex' => 'YouTube does not allow < or > in the title.',
            'description.not_regex' => 'YouTube does not allow < or > in the description.',
            'tags.*.not_regex' => 'Tags cannot contain <, > or commas.',
            'tags.*.distinct' => 'Each tag may appear only once.',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var list<string> $tags */
                $tags = array_values(array_filter((array) $this->input('tags', []), is_string(...)));

                if (YouTubeDraft::tagsLength($tags) > YouTubeDraft::TAGS_MAX_TOTAL) {
                    $validator->errors()->add('tags', 'YouTube allows at most '.YouTubeDraft::TAGS_MAX_TOTAL.' characters of tags in total.');
                }

                $user = $this->user();
                $record = $this->record();

                if (! $user instanceof User || $record === null) {
                    return;
                }

                if (! $user->youtubeAccount()->exists()) {
                    $validator->errors()->add('channel', 'Connect a YouTube channel in Settings → YouTube first.');
                }

                $active = array_values(array_filter(PublicationStatus::cases(), fn (PublicationStatus $status): bool => $status->isActive()));

                if ($record->youtubePublications()->whereIn('status', $active)->exists()) {
                    $validator->errors()->add('channel', 'This record is already being published. Wait for it to finish.');
                }

                if (app(YouTubeDraft::class)->sourceFile($record) === null) {
                    $validator->errors()->add('channel', 'The record has no video file YouTube can take.');
                }
            },
        ];
    }

    /**
     * The encoder's choices, cleaned for YouTube.
     *
     * @return array{privacy_status: string, title: string, description: string|null, tags: list<string>|null, category_id: string|null, made_for_kids: bool, with_thumbnail: bool}
     */
    public function choices(): array
    {
        $tags = $this->validated('tags');

        return [
            'privacy_status' => (string) $this->validated('privacy_status'),
            'title' => YouTubeDraft::clean((string) $this->validated('title')),
            'description' => YouTubeDraft::clean((string) $this->validated('description'), keepLines: true),
            'tags' => is_array($tags) ? array_values(array_filter(array_map(fn (mixed $tag): string => trim((string) $tag), $tags))) : [],
            'category_id' => (string) $this->validated('category_id'),
            'made_for_kids' => $this->boolean('made_for_kids'),
            'with_thumbnail' => $this->boolean('with_thumbnail'),
        ];
    }

    private function record(): ?Record
    {
        $record = $this->route('record');

        return $record instanceof Record ? $record : null;
    }
}
