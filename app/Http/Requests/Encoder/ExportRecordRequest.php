<?php

namespace App\Http\Requests\Encoder;

use App\Enums\ExportFormat;
use App\Enums\TaskStatus;
use App\Exporters\ExportOptions;
use App\Models\ExportSchema;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /records/{record}/export?format=&schema=&language=&timestamps=&preview=
 *
 * - `schema`: one of the user's own export schemas (JSON only).
 * - `language`: a finished transcript of this record (original or translation).
 * - `preview`: return the text inline for the Export panel instead of a download.
 */
class ExportRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->record() !== null && ($this->user()?->can('export', $this->record()) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::enum(ExportFormat::class)],
            'schema' => [
                'nullable',
                'integer',
                Rule::exists('export_schemas', 'id')->where('user_id', $this->user()?->id),
            ],
            'language' => [
                'nullable',
                'string',
                'max:16',
                Rule::exists('record_transcripts', 'language')
                    ->where('record_id', $this->record()?->id)
                    ->where('status', TaskStatus::Done->value),
            ],
            'timestamps' => ['nullable', 'boolean'],
            'preview' => ['nullable', 'boolean'],
        ];
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::tryFrom((string) $this->validated('format')) ?? ExportFormat::Json;
    }

    public function schema(): ?ExportSchema
    {
        $id = $this->validated('schema');

        return $id === null || $this->exportFormat() !== ExportFormat::Json
            ? null
            : ExportSchema::query()->whereKey((int) $id)->first(['id', 'user_id', 'name', 'fields']);
    }

    public function exportOptions(?ExportSchema $schema): ExportOptions
    {
        $language = $this->validated('language');

        return new ExportOptions(
            fields: $schema?->fields,
            language: is_string($language) ? $language : null,
            timestamps: $this->boolean('timestamps'),
        );
    }

    private function record(): ?Record
    {
        $record = $this->route('record');

        return $record instanceof Record ? $record : null;
    }
}
