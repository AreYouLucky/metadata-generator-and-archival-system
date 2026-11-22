<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A named scene: a group of shots, from the first shot's start to the last
 * one's end. Also used to change one (the whole scene is sent).
 */
class StoreSceneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');
        $duration = $record->masterFile()->first()?->technicalMetadata()->value('duration');
        // A little slack: shot ends come from the decoder and can pass the probed duration.
        $end = $duration !== null ? ['max:'.((float) $duration + 1)] : [];

        return [
            'start' => ['required', 'numeric', 'min:0'],
            'end' => ['required', 'numeric', 'gt:start', ...$end],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end.gt' => 'A scene must end after it starts.',
            'end.max' => 'The scene ends past the end of the video.',
            'label.required' => 'Name the scene.',
        ];
    }

    /**
     * @return array{start: float, end: float, label: string, description: string|null}
     */
    public function scene(): array
    {
        $description = trim((string) $this->validated('description'));

        return [
            'start' => round((float) $this->validated('start'), 3),
            'end' => round((float) $this->validated('end'), 3),
            'label' => trim((string) $this->validated('label')),
            'description' => $description !== '' ? $description : null,
        ];
    }
}
