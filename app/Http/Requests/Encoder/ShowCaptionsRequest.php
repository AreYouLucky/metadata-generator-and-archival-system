<?php

namespace App\Http\Requests\Encoder;

use App\Enums\TaskStatus;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The player's caption track for one transcript language (the original's or a
 * finished translation's). The route's {language} is validated like any input.
 */
class ShowCaptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->record() !== null && ($this->user()?->can('view', $this->record()) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['language' => $this->route('language')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'language' => [
                'required',
                'string',
                'max:16',
                Rule::exists('record_transcripts', 'language')
                    ->where('record_id', $this->record()?->id)
                    ->where('status', TaskStatus::Done->value),
            ],
        ];
    }

    private function record(): ?Record
    {
        $record = $this->route('record');

        return $record instanceof Record ? $record : null;
    }
}
