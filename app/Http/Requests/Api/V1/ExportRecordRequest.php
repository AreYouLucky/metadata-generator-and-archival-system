<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ApiTokenAbility;
use App\Enums\ExportFormat;
use App\Enums\TaskStatus;
use App\Exporters\ExportOptions;
use App\Models\ExportSchema;
use App\Models\Record;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/records/{record}/export?format=&schema=&language=&timestamps=
 *
 * An export through the API leaves the archive, so besides `records:read`
 * and the owner's own access, the record must pass the distribution policy
 * (approved, unclassified, public rights, no embargo).
 */
class ExportRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $record = $this->record();

        return $user !== null
            && $record !== null
            && $user->tokenCan(ApiTokenAbility::ReadRecords->value)
            && $user->can('export', $record)
            && Gate::forUser($user)->allows('distribute', $record);
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

    /**
     * Release forms and other personal attachments are never part of it.
     */
    public function exportOptions(?ExportSchema $schema): ExportOptions
    {
        $language = $this->validated('language');

        return new ExportOptions(
            fields: $schema?->fields,
            language: is_string($language) ? $language : null,
            timestamps: $this->boolean('timestamps'),
            forDistribution: true,
        );
    }

    private function record(): ?Record
    {
        $record = $this->route('record');

        return $record instanceof Record ? $record : null;
    }
}
