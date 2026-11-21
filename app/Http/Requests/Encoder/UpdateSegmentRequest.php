<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('update', $record) ?? false);
    }

    /**
     * Text, speaker or both. A speaker must belong to the same record; null clears it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');

        return [
            'text' => ['sometimes', 'required', 'string', 'max:5000'],
            'speaker_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('record_speakers', 'id')->where('record_id', $record->id),
            ],
        ];
    }
}
