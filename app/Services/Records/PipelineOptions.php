<?php

namespace App\Services\Records;

use App\Enums\Preset;

/**
 * What the encoder chose for AI generation, and how it is sent to the AI service.
 */
final readonly class PipelineOptions
{
    public const int TITLE_COUNT = 3;

    /**
     * The visual flags are chosen on upload (and again on retry): frames,
     * scenes and descriptions; on-screen text; objects and face counts.
     * OCR and objects only run with visual analysis. `diarization` labels
     * the speakers of the transcript (Phase 6).
     */
    public function __construct(
        public Preset $preset = Preset::Generic,
        public ?string $customPrompt = null,
        public bool $visualAnalysis = false,
        public bool $ocr = false,
        public bool $objectDetection = false,
        public bool $diarization = false,
    ) {}

    /**
     * Build the options from validated request input (upload, batch, retry).
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $prompt = trim((string) ($input['custom_prompt'] ?? ''));

        return new self(
            Preset::from((string) $input['preset']),
            $prompt !== '' ? $prompt : null,
            visualAnalysis: (bool) ($input['visual_analysis'] ?? false),
            ocr: (bool) ($input['ocr'] ?? false),
            objectDetection: (bool) ($input['object_detection'] ?? false),
            diarization: (bool) ($input['diarization'] ?? false),
        );
    }

    /**
     * The options as kept on the record (`records.pipeline_options`), so a URL
     * import can start processing once downloaded and a retry can offer them.
     *
     * @return array{preset: string, custom_prompt: string|null, visual_analysis: bool, ocr: bool, object_detection: bool, diarization: bool}
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset->value,
            'custom_prompt' => $this->customPrompt,
            'visual_analysis' => $this->visualAnalysis,
            'ocr' => $this->ocr,
            'object_detection' => $this->objectDetection,
            'diarization' => $this->diarization,
        ];
    }

    /**
     * Options stored on a record; defaults when none were kept (records from
     * before Phase 6) or the preset has since been switched off.
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromStored(?array $stored): self
    {
        if ($stored === null || ! in_array($stored['preset'] ?? null, self::enabledPresets(), true)) {
            return new self;
        }

        return self::fromInput($stored);
    }

    /**
     * Every flag as the AI service's JobOptions expects it.
     *
     * @return array{visual_analysis: bool, ocr: bool, object_detection: bool, diarization: bool}
     */
    public function jobFlags(): array
    {
        return [...$this->visualFlags(), 'diarization' => $this->diarization];
    }

    /**
     * The flags as the AI service's JobOptions expects them.
     *
     * @return array{visual_analysis: bool, ocr: bool, object_detection: bool}
     */
    public function visualFlags(): array
    {
        return [
            'visual_analysis' => $this->visualAnalysis,
            'ocr' => $this->visualAnalysis && $this->ocr,
            'object_detection' => $this->visualAnalysis && $this->objectDetection,
        ];
    }

    /**
     * Validation rules for the flags (upload and retry).
     *
     * @return array<string, array<int, string>>
     */
    public static function visualRules(): array
    {
        return [
            'visual_analysis' => ['sometimes', 'boolean'],
            'ocr' => ['sometimes', 'boolean'],
            'object_detection' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Rules for every processing switch chosen on upload, batch and retry.
     *
     * @return array<string, array<int, string>>
     */
    public static function processingRules(): array
    {
        return [...self::visualRules(), 'diarization' => ['sometimes', 'boolean']];
    }

    /**
     * The preset's limits from config/presets.php, in the shape the AI service expects.
     *
     * @return array{name: string, label: string, title_max: int|null, description_max: int, tags_max_count: int|null, tags_max_chars: int|null, hashtags_min: int, hashtags_max: int, chapters: bool, guidance: string}
     */
    public function presetRules(): array
    {
        /** @var array{label: string, title_max: int|null, description_max: int, tags_max_count: int|null, tags_max_chars: int|null, hashtags_min: int, hashtags_max: int, chapters: bool, guidance: string} $rules */
        $rules = config("presets.{$this->preset->value}");

        return ['name' => $this->preset->value, ...$rules];
    }

    /**
     * Presets switched on in config/presets.php.
     *
     * @return list<string>
     */
    public static function enabledPresets(): array
    {
        /** @var array<string, mixed> $presets */
        $presets = config('presets');

        return array_keys($presets);
    }
}
