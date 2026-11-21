<?php

namespace App\Http\Requests\Encoder;

use App\Enums\Preset;
use App\Models\Record;
use App\Services\Records\PipelineOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Record::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'uuid'],
            // Every record is filed under a project (event); the series is optional
            // but must belong to that project.
            'project_id' => ['required', 'string', Rule::exists('projects', 'id')->withoutTrashed()],
            'project_series_id' => [
                'nullable',
                'string',
                Rule::exists('project_series', 'id')->where('project_id', (string) $this->input('project_id')),
            ],
            // Producing agency and, optionally, one of its production units.
            'agency_id' => ['nullable', 'integer', 'required_with:division_id', Rule::exists('production_agencies', 'id')->whereNull('parent_id')],
            'division_id' => [
                'nullable',
                'integer',
                Rule::exists('production_agencies', 'id')->where('parent_id', (int) $this->input('agency_id')),
            ],
            'title' => ['nullable', 'string', 'max:250'],
            'preset' => ['required', Rule::in(PipelineOptions::enabledPresets())],
            'custom_prompt' => ['nullable', 'string', 'max:2000'],
            'expected_md5' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{32}$/'],
            'expected_sha256' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Choose the project (event) this video belongs to.',
            'project_series_id.exists' => 'That series does not belong to the chosen project.',
            'agency_id.required_with' => 'Choose the agency of that production unit.',
            'division_id.exists' => 'That production unit does not belong to the chosen agency.',
            'expected_md5.regex' => 'An MD5 checksum is 32 hexadecimal characters.',
            'expected_sha256.regex' => 'A SHA-256 checksum is 64 hexadecimal characters.',
        ];
    }

    public function pipelineOptions(): PipelineOptions
    {
        $prompt = trim((string) $this->validated('custom_prompt'));

        return new PipelineOptions(Preset::from($this->validated('preset')), $prompt !== '' ? $prompt : null);
    }
}
