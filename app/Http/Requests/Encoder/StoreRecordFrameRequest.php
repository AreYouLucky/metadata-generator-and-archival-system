<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use App\Models\RecordFrame;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Add a key frame at a time in the video (the player's position), with an
 * optional description. One frame per millisecond, as the pipeline stores them.
 */
class StoreRecordFrameRequest extends FormRequest
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

        return [
            'time_sec' => ['required', 'numeric', 'min:0', ...($duration !== null ? ['max:'.(float) $duration] : [])],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Record $record */
                $record = $this->route('record');

                if ($record->frames()->where('time_sec', $this->seconds())->exists()) {
                    $validator->errors()->add('time_sec', 'There is already a key frame at this time.');
                }
            },
        ];
    }

    public function seconds(): float
    {
        return (float) RecordFrame::timeKey((float) $this->validated('time_sec', $this->input('time_sec')));
    }

    public function description(): ?string
    {
        $description = trim((string) $this->validated('description'));

        return $description !== '' ? $description : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'time_sec.max' => 'That time is past the end of the video.',
        ];
    }
}
