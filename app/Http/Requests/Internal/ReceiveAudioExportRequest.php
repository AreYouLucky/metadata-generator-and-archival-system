<?php

namespace App\Http\Requests\Internal;

use App\Models\Record;
use App\Services\Records\AudioExports;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An audio export finished ({format, path, file_size}) or failed
 * ({format, error}), signed by the AI service. The path must be the one
 * this record's export of that format is stored at.
 */
class ReceiveAudioExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Record $record */
        $record = $this->route('record');
        $format = (string) $this->input('format');

        return [
            'format' => ['required', Rule::in(array_keys(AudioExports::FORMATS))],
            'error' => ['nullable', 'string', 'max:1000'],
            'path' => ['required_without:error', 'string', Rule::in([AudioExports::path($record, $format)])],
            'file_size' => ['required_with:path', 'integer', 'min:0'],
        ];
    }
}
