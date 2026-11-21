<?php

namespace App\Http\Requests\Internal;

use App\Enums\PipelineStage;
use App\Enums\ScanType;
use App\Enums\UsageKind;
use App\Services\Records\PipelineOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One stage's result from the AI service. The rules depend on the {stage}
 * route value, and only validated keys are stored, so nothing unexpected
 * reaches the database even from a correctly signed caller.
 */
class ReceiveStageResultRequest extends FormRequest
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
        return match ($this->stage()) {
            PipelineStage::Checksum => [
                'md5' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'],
                'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
                'file_size' => ['required', 'integer', 'min:0'],
            ],
            PipelineStage::Probe => $this->probeRules(),
            PipelineStage::ExtractAudio => [
                'audio_path' => ['required', 'string', 'max:1024'],
            ],
            PipelineStage::Transcribe => [
                'provider' => ['required', 'string', 'max:32'],
                'model' => ['required', 'string', 'max:255'],
                'language' => ['nullable', 'string', 'regex:/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/'],
                'language_confidence' => ['nullable', 'numeric', 'between:0,1'],
                'duration' => ['nullable', 'numeric', 'min:0'],
                'segments' => ['present', 'array', 'max:50000'],
                'segments.*.start' => ['required', 'numeric', 'min:0'],
                'segments.*.end' => ['required', 'numeric', 'min:0'],
                'segments.*.text' => ['required', 'string', 'max:5000'],
                ...$this->usageRules(),
            ],
            PipelineStage::GenerateMetadata => [
                'preset' => ['required', Rule::in(PipelineOptions::enabledPresets())],
                'custom_prompt' => ['nullable', 'string', 'max:2000'],
                'model' => ['required', 'string', 'max:255'],
                ...self::metadataRules('metadata.'),
                ...$this->usageRules(),
            ],
            default => [],
        };
    }

    /**
     * @return array{md5: string, sha256: string, file_size: int}
     */
    public function checksumResult(): array
    {
        return [
            'md5' => (string) $this->validated('md5'),
            'sha256' => (string) $this->validated('sha256'),
            'file_size' => (int) $this->validated('file_size'),
        ];
    }

    /**
     * @return array{language: string|null, language_confidence: float|null, segments: list<array{start: float, end: float, text: string}>, usage: list<array{kind: string, quantity: float, estimated_cost: float|null}>}
     */
    public function transcriptResult(): array
    {
        /** @var array<int, array{start: float|int|string, end: float|int|string, text: string}> $segments */
        $segments = $this->validated('segments');
        $confidence = $this->validated('language_confidence');

        return [
            'language' => $this->validated('language'),
            'language_confidence' => $confidence === null ? null : (float) $confidence,
            'segments' => array_values(array_map(fn (array $segment): array => [
                'start' => (float) $segment['start'],
                'end' => (float) $segment['end'],
                'text' => $segment['text'],
            ], $segments)),
            'usage' => $this->usage(),
        ];
    }

    /**
     * @return array{preset: string, custom_prompt: string|null, model: string, metadata: array<string, mixed>, usage: list<array{kind: string, quantity: float, estimated_cost: float|null}>}
     */
    public function metadataResult(): array
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $this->validated('metadata');

        return [
            'preset' => (string) $this->validated('preset'),
            'custom_prompt' => $this->validated('custom_prompt'),
            'model' => (string) $this->validated('model'),
            'metadata' => $metadata,
            'usage' => $this->usage(),
        ];
    }

    /**
     * @return list<array{kind: string, quantity: float, estimated_cost: float|null}>
     */
    private function usage(): array
    {
        /** @var array<int, array{kind: string, quantity: float|int|string, estimated_cost?: float|int|string|null}> $usage */
        $usage = $this->validated('usage', []);

        return array_values(array_map(fn (array $item): array => [
            'kind' => $item['kind'],
            'quantity' => (float) $item['quantity'],
            'estimated_cost' => isset($item['estimated_cost']) ? (float) $item['estimated_cost'] : null,
        ], $usage));
    }

    public function stage(): PipelineStage
    {
        /** @var PipelineStage $stage */
        $stage = $this->route('stage');

        return $stage;
    }

    /**
     * Rules for an `ai` metadata block (PROJECT_SPEC §10), shared with the
     * workspace's metadata edit request.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function metadataRules(string $prefix = ''): array
    {
        $parent = $prefix === '' ? [] : [rtrim($prefix, '.') => ['required', 'array']];

        return [
            ...$parent,
            "{$prefix}titles" => ['present', 'array', 'max:10'],
            "{$prefix}titles.*" => ['string', 'max:300'],
            "{$prefix}description" => ['present', 'nullable', 'string', 'max:10000'],
            "{$prefix}summary" => ['present', 'nullable', 'string', 'max:5000'],
            "{$prefix}tags" => ['present', 'array', 'max:50'],
            "{$prefix}tags.*" => ['string', 'max:100'],
            "{$prefix}keywords" => ['present', 'array', 'max:50'],
            "{$prefix}keywords.*" => ['string', 'max:100'],
            "{$prefix}hashtags" => ['present', 'array', 'max:30'],
            "{$prefix}hashtags.*" => ['string', 'max:100'],
            "{$prefix}chapters" => ['present', 'array', 'max:200'],
            "{$prefix}chapters.*.start" => ['required', 'numeric', 'min:0'],
            "{$prefix}chapters.*.title" => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function probeRules(): array
    {
        $text = fn (int $max): array => ['nullable', 'string', "max:{$max}"];

        return [
            'file_format' => $text(255),
            'duration' => ['nullable', 'numeric', 'min:0'],
            'bitrate' => ['nullable', 'integer', 'min:0'],
            'start_timecode' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}:\d{2}[:;.]\d{2,3}$/'],
            'video_codec' => $text(255),
            'video_profile' => $text(255),
            'resolution' => $text(32),
            'frame_rate' => $text(32),
            'aspect_ratio' => $text(16),
            'scan_type' => ['nullable', Rule::enum(ScanType::class)],
            'bit_depth' => ['nullable', 'integer', 'between:1,64'],
            'chroma_subsampling' => $text(16),
            'color_space' => $text(32),
            'color_primaries' => $text(32),
            'color_transfer' => $text(32),
            'audio_codec' => $text(255),
            'audio_channels' => ['nullable', 'integer', 'between:0,255'],
            'audio_sample_rate' => ['nullable', 'integer', 'min:0'],
            'audio_tracks' => ['present', 'array', 'max:64'],
            'audio_tracks.*.index' => ['required', 'integer', 'min:1'],
            'audio_tracks.*.codec' => $text(255),
            'audio_tracks.*.channels' => ['nullable', 'integer', 'min:0'],
            'audio_tracks.*.sampleRate' => ['nullable', 'integer', 'min:0'],
            'audio_tracks.*.language' => $text(16),
            'container_tags' => ['present', 'array'],
            'container_tags.creation_time' => $text(64),
            'container_tags.location' => $text(64),
            'raw_probe' => ['present', 'array'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function usageRules(): array
    {
        return [
            'usage' => ['present', 'array', 'max:20'],
            'usage.*.kind' => ['required', Rule::in([UsageKind::TranscriptionMinutes->value, UsageKind::LlmInputTokens->value, UsageKind::LlmOutputTokens->value])],
            'usage.*.quantity' => ['required', 'numeric', 'min:0'],
            'usage.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
