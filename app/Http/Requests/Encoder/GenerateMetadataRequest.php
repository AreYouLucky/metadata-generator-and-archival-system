<?php

namespace App\Http\Requests\Encoder;

use App\Enums\Preset;
use App\Models\Record;
use App\Services\Records\PipelineOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('generate', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preset' => ['required', Rule::in(PipelineOptions::enabledPresets())],
            'custom_prompt' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function pipelineOptions(): PipelineOptions
    {
        $prompt = trim((string) $this->validated('custom_prompt'));

        return new PipelineOptions(Preset::from($this->validated('preset')), $prompt !== '' ? $prompt : null);
    }
}
