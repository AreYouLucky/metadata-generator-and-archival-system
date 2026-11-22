<?php

namespace App\Http\Requests\Encoder;

use App\Enums\SubjectType;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A subject an encoder adds by hand (it is confirmed at once).
 */
class StoreSubjectRequest extends FormRequest
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
        $record = $this->route('record');

        return [
            'subject' => [
                'required',
                'string',
                'max:250',
                Rule::unique('record_subjects', 'subject')->where('record_id', $record instanceof Record ? $record->id : null),
            ],
            'type' => ['required', Rule::enum(SubjectType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['subject.unique' => 'That subject is already on this record.'];
    }
}
