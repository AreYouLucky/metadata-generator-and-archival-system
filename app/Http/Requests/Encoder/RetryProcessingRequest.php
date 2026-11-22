<?php

namespace App\Http\Requests\Encoder;

use App\Enums\PipelineStage;
use App\Http\Requests\Concerns\ChecksDailyProcessingLimit;
use App\Models\Record;
use App\Services\Records\PipelineOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Same choices as a regeneration (preset, instructions) plus the processing
 * switches, which the form fills in from the upload's. Without `stage` the
 * whole pipeline runs again; with one, that stage and the stages that read
 * its results (PROJECT_SPEC §8, POST /records/{record}/retry?stage=).
 * Refused once today's processing minutes are used up.
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
        return [
            ...parent::rules(),
            ...PipelineOptions::processingRules(),
            'stage' => ['nullable', Rule::in(array_map(fn (PipelineStage $stage): string => $stage->value, PipelineStage::tracked()))],
        ];
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
            diarization: (bool) $this->validated('diarization', false),
        );
    }

    public function stage(): ?PipelineStage
    {
        $stage = $this->validated('stage');

        return is_string($stage) ? PipelineStage::from($stage) : null;
    }
}
