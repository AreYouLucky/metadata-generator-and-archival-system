<?php

namespace App\Http\Requests\Encoder;

use App\Models\Record;
use App\Services\Records\AudioExports;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /records/{record}/audio-export/{format}: download a finished audio
 * export. The route's {format} is validated like any input.
 */
class ShowAudioExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('export', $record) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['format' => $this->route('format')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(array_keys(AudioExports::FORMATS))],
        ];
    }

    public function audioFormat(): string
    {
        return (string) $this->validated('format');
    }
}
