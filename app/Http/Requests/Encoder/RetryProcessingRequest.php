<?php

namespace App\Http\Requests\Encoder;

use App\Http\Requests\Concerns\ChecksDailyProcessingLimit;
use App\Models\Record;
use App\Services\Records\PipelineOptions;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Same choices as a regeneration (preset, instructions) plus the visual
 * analysis switches: the upload's own choices aren't stored, so the encoder
 * picks again when retrying. Refused once today's processing minutes are
 * used up.
 */
class RetryProcessingRequest extends GenerateMetadataRequest
{
    use ChecksDailyProcessingLimit;

    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record instanceof Record && ($this->user()?->can('retry', $record) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...parent::rules(), ...PipelineOptions::visualRules()];
    }

    public function pipelineOptions(): PipelineOptions
    {
        $options = parent::pipelineOptions();

        return new PipelineOptions(
            $options->preset,
            $options->customPrompt,
            visualAnalysis: (bool) $this->validated('visual_analysis', false),
            ocr: (bool) $this->validated('ocr', false),
            objectDetection: (bool) $this->validated('object_detection', false),
        );
    }
}
