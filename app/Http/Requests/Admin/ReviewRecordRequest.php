<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReviewAction;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An admin's decision on a submitted record. Notes are required when
 * rejecting or requesting changes, so the encoder knows what to fix.
 */
class ReviewRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('review', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(ReviewAction::class)],
            'notes' => [
                Rule::requiredIf(fn (): bool => ReviewAction::tryFrom((string) $this->input('action'))?->needsNotes() ?? false),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Add a note so the encoder knows what to change.',
        ];
    }

    public function action(): ReviewAction
    {
        return ReviewAction::from((string) $this->validated('action'));
    }
}
