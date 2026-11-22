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
     * OCR and objects only run with visual analysis.
     */
    public function __construct(
        public Preset $preset = Preset::Generic,
        public ?string $customPrompt = null,
        public bool $visualAnalysis = false,
        public bool $ocr = false,
        public bool $objectDetection = false,
    ) {}

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
