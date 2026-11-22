<?php

namespace App\Http\Requests\Encoder;

use App\Enums\SubjectType;
use App\Models\Record;
use App\Models\RecordSubject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirm a suggested subject, or correct its text or type.
 */
class UpdateSubjectRequest extends FormRequest
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
        $subject = $this->route('subject');

        return [
            'subject' => [
                'sometimes',
                'required',
                'string',
                'max:250',
                Rule::unique('record_subjects', 'subject')
                    ->where('record_id', $record instanceof Record ? $record->id : null)
                    ->ignore($subject instanceof RecordSubject ? $subject->id : null),
            ],
            'type' => ['sometimes', 'required', Rule::enum(SubjectType::class)],
            'is_confirmed' => ['sometimes', 'required', 'boolean'],
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
