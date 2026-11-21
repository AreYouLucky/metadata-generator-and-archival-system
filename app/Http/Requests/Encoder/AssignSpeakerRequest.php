<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSpeakerRequest extends FormRequest
{
    /** A three-hour transcript has a few thousand lines. */
    public const MAX_SEGMENTS = 10000;

    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * Many lines at once ("apply to all"). Ids from other records are ignored by
     * the controller; the speaker must belong to this record, null clears it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');

        return [
            'segment_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_SEGMENTS],
            'segment_ids.*' => ['integer', 'distinct'],
            'speaker_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('record_speakers', 'id')->where('record_id', $record->id),
            ],
        ];
    }
}
