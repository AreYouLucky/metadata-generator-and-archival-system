<?php

namespace App\Http\Requests\Internal;

use App\Enums\PipelineStage;
use App\Enums\ScanType;
use App\Enums\SubjectType;
use App\Enums\UsageKind;
use App\Models\Record;
use App\Models\RecordFrame;
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
    /**
     * Title options a generation may hold (three generated, plus "Generate more").
     */
    public const int MAX_TITLES = 12;

    /**
     * Bounds for the visual stages (the AI service caps them lower).
     */
    public const int MAX_SHOTS = 5000;

    public const int MAX_FRAMES = 200;

    /**
     * Speaker turns from diarization (about one per sentence at most).
     */
    public const int MAX_TURNS = 50000;

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
            PipelineStage::Diarize => [
                'provider' => ['required', 'string', 'max:32'],
                'model' => ['nullable', 'string', 'max:255'],
                'turns' => ['present', 'array', 'max:'.self::MAX_TURNS],
                'turns.*.start' => ['required', 'numeric', 'min:0'],
                'turns.*.end' => ['required', 'numeric', 'min:0'],
                'turns.*.speaker' => ['required', 'string', 'max:32'],
                ...$this->usageRules(),
            ],
            PipelineStage::Proxy => [
                'path' => ['required', 'string', 'max:1024', 'regex:/^records\/[0-9a-z]{26}\/proxy\/[A-Za-z0-9._-]+\.mp4$/i'],
                'md5' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'],
                'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
                'file_size' => ['required', 'integer', 'min:0'],
                ...self::prefixed('probe.', $this->probeRules(withRaw: false)),
            ],
            PipelineStage::GenerateMetadata => [
                'preset' => ['required', Rule::in(PipelineOptions::enabledPresets())],
                'custom_prompt' => ['nullable', 'string', 'max:2000'],
                'model' => ['required', 'string', 'max:255'],
                ...self::metadataRules('metadata.'),
                ...$this->usageRules(),
            ],
            PipelineStage::Scenes => [
                'shots' => ['present', 'array', 'max:'.self::MAX_SHOTS],
                'shots.*.start' => ['required', 'numeric', 'min:0'],
                'shots.*.end' => ['required', 'numeric', 'min:0'],
            ],
            PipelineStage::Frames => [
                'frames' => ['present', 'array', 'max:'.self::MAX_FRAMES],
                'frames.*.time_sec' => ['required', 'numeric', 'min:0'],
                // Only inside this record's frames folder.
                'frames.*.path' => ['required', 'string', 'max:255', 'regex:'.$this->framePathPattern()],
                'frames.*.is_scene_start' => ['required', 'boolean'],
                'thumbnail_index' => ['nullable', 'integer', 'min:0'],
            ],
            PipelineStage::Vision => [
                'model' => ['required', 'string', 'max:255'],
                ...$this->frameRules(),
                'frames.*.description' => ['nullable', 'string', 'max:1000'],
                ...$this->usageRules(),
            ],
            PipelineStage::Ocr => [
                ...$this->frameRules(),
                'frames.*.text' => ['nullable', 'string', 'max:5000'],
            ],
            PipelineStage::Objects => [
                ...$this->frameRules(),
                'frames.*.objects' => ['present', 'array', 'max:20'],
                'frames.*.objects.*.label' => ['required', 'string', 'max:64'],
                'frames.*.objects.*.confidence' => ['required', 'numeric', 'between:0,1'],
                'frames.*.face_count' => ['nullable', 'integer', 'between:0,1000'],
            ],
            default => [],
        };
    }

    /**
     * Shots from the `scenes` stage (end never before start).
     *
     * @return list<array{start: float, end: float}>
     */
    public function shotsResult(): array
    {
        /** @var array<int, array{start: float|int|string, end: float|int|string}> $shots */
        $shots = $this->validated('shots');

        return array_values(array_map(fn (array $shot): array => [
            'start' => (float) $shot['start'],
            'end' => max((float) $shot['start'], (float) $shot['end']),
        ], $shots));
    }

    /**
     * Frames from the `frames` stage and the suggested default thumbnail.
     *
     * @return array{frames: list<array{time_sec: float, path: string, is_scene_start: bool}>, thumbnail_index: int|null}
     */
    public function framesResult(): array
    {
        /** @var array<int, array{time_sec: float|int|string, path: string, is_scene_start: bool|int|string}> $frames */
        $frames = $this->validated('frames');
        $thumbnail = $this->validated('thumbnail_index');

        return [
            'frames' => array_values(array_map(fn (array $frame): array => [
                'time_sec' => (float) $frame['time_sec'],
                'path' => $frame['path'],
                'is_scene_start' => (bool) $frame['is_scene_start'],
            ], $frames)),
            'thumbnail_index' => $thumbnail === null ? null : (int) $thumbnail,
        ];
    }

    /**
     * Per-frame results of `vision`, `ocr` or `objects`, as the columns they
     * fill, keyed by the frame's time (how the AI service names a frame).
     *
     * @return array{updates: array<string, array<string, mixed>>, usage: list<array{kind: string, quantity: float, estimated_cost: float|null}>}
     */
    public function frameAnalysis(): array
    {
        /** @var array<int, array<string, mixed>> $frames */
        $frames = $this->validated('frames');
        $updates = [];

        foreach ($frames as $frame) {
            $key = RecordFrame::timeKey((float) $frame['time_sec']);
            $updates[$key] = match ($this->stage()) {
                PipelineStage::Vision => ['description' => self::text($frame['description'] ?? null)],
                PipelineStage::Ocr => ['ocr_text' => self::text($frame['text'] ?? null)],
                default => [
                    'objects' => array_values(array_map(fn (array $object): array => [
                        'label' => (string) $object['label'],
                        'confidence' => round((float) $object['confidence'], 3),
                    ], (array) ($frame['objects'] ?? []))),
                    'face_count' => isset($frame['face_count']) ? (int) $frame['face_count'] : null,
                ],
            };
        }

        return ['updates' => $updates, 'usage' => $this->usage()];
    }

    private static function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function frameRules(): array
    {
        return [
            'frames' => ['present', 'array', 'max:'.self::MAX_FRAMES],
            'frames.*.time_sec' => ['required', 'numeric', 'min:0'],
        ];
    }

    private function framePathPattern(): string
    {
        $record = $this->route('record');
        $id = $record instanceof Record ? preg_quote($record->id, '/') : 'none';

        return '/^records\/'.$id.'\/frames\/[A-Za-z0-9_-]{1,64}\.jpg$/';
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
     * Speaker turns from the `diarize` stage (end never before start).
     *
     * @return array{turns: list<array{start: float, end: float, speaker: string}>, usage: list<array{kind: string, quantity: float, estimated_cost: float|null}>}
     */
    public function speakersResult(): array
    {
        /** @var array<int, array{start: float|int|string, end: float|int|string, speaker: string}> $turns */
        $turns = $this->validated('turns');

        return [
            'turns' => array_values(array_map(fn (array $turn): array => [
                'start' => (float) $turn['start'],
                'end' => max((float) $turn['start'], (float) $turn['end']),
                'speaker' => $turn['speaker'],
            ], $turns)),
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
     * @return array{path: string, md5: string, sha256: string, file_size: int, probe: array<string, mixed>}
     */
    public function proxyResult(): array
    {
        /** @var array<string, mixed> $probe */
        $probe = $this->validated('probe');

        return [
            'path' => (string) $this->validated('path'),
            'md5' => (string) $this->validated('md5'),
            'sha256' => (string) $this->validated('sha256'),
            'file_size' => (int) $this->validated('file_size'),
            'probe' => $probe,
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
            // Room for "Generate more" options on top of the first three.
            "{$prefix}titles" => ['present', 'array', 'max:'.self::MAX_TITLES],
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
            // Phase 2. Optional, so generations stored before them still save.
            "{$prefix}quotes" => ['sometimes', 'array', 'max:20'],
            "{$prefix}quotes.*.start" => ['required', 'numeric', 'min:0'],
            "{$prefix}quotes.*.end" => ['required', 'numeric', 'min:0'],
            "{$prefix}quotes.*.speaker" => ['nullable', 'string', 'max:100'],
            "{$prefix}quotes.*.text" => ['required', 'string', 'max:1000'],
            "{$prefix}tone" => ['sometimes', 'nullable', 'string', 'max:40'],
            "{$prefix}contentFlags" => ['sometimes', 'nullable', 'array'],
            "{$prefix}contentFlags.profanity" => ["required_with:{$prefix}contentFlags", 'boolean'],
            "{$prefix}contentFlags.sensitiveTopics" => ["present_with:{$prefix}contentFlags", 'array', 'max:10'],
            "{$prefix}contentFlags.sensitiveTopics.*" => ['string', 'max:60'],
            // Phase 3 suggestions (stored as unconfirmed archive entries, not edited here).
            "{$prefix}suggestedSubjects" => ['sometimes', 'array', 'max:20'],
            "{$prefix}suggestedSubjects.*.name" => ['required', 'string', 'max:250'],
            "{$prefix}suggestedSubjects.*.type" => ['required', Rule::enum(SubjectType::class)],
            "{$prefix}suggestedLocation" => ['sometimes', 'nullable', 'array'],
            "{$prefix}suggestedLocation.text" => ["required_with:{$prefix}suggestedLocation", 'string', 'max:250'],
            "{$prefix}suggestedLocation.region" => ['nullable', 'string', 'max:120'],
            "{$prefix}suggestedLocation.province" => ['nullable', 'string', 'max:120'],
            "{$prefix}suggestedLocation.city" => ['nullable', 'string', 'max:120'],
            "{$prefix}suggestedLocation.barangay" => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    private static function prefixed(string $prefix, array $rules): array
    {
        $result = [rtrim($prefix, '.') => ['required', 'array']];

        foreach ($rules as $key => $rule) {
            $result[$prefix.$key] = $rule;
        }

        return $result;
    }

    /**
     * Rules for a probe summary (also used for added files and proxies).
     *
     * @return array<string, array<int, mixed>>
     */
    public function probeRules(bool $withRaw = true): array
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
            ...($withRaw ? ['raw_probe' => ['present', 'array']] : []),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function usageRules(): array
    {
        return [
            'usage' => ['present', 'array', 'max:20'],
            'usage.*.kind' => ['required', Rule::in([UsageKind::TranscriptionMinutes->value, UsageKind::LlmInputTokens->value, UsageKind::LlmOutputTokens->value, UsageKind::VisionFrames->value])],
            'usage.*.quantity' => ['required', 'numeric', 'min:0'],
            'usage.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
