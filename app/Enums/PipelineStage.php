<?php

namespace App\Enums;

/**
 * Processing steps run by the AI service.
 */
enum PipelineStage: string
{
    // Downloading a URL import (Phase 6); only URL records have this stage.
    case Import = 'import';
    case Probe = 'probe';
    case Checksum = 'checksum';
    case ExtractAudio = 'extract_audio';
    case Transcribe = 'transcribe';
    case Diarize = 'diarize';
    case Proxy = 'proxy';
    case Scenes = 'scenes';
    case Frames = 'frames';
    case Vision = 'vision';
    case Ocr = 'ocr';
    case Objects = 'objects';
    case GenerateMetadata = 'generate_metadata';

    /**
     * The stages the pipeline runs today, in display order (checksums first,
     * before anything else reads the file; the access copy last, so a long
     * transcode never delays the transcript). The audio stages and the visual
     * stages run side by side after probing; metadata waits for both. Later
     * phases add their stages here.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [
            self::Checksum,
            self::Probe,
            self::ExtractAudio,
            self::Transcribe,
            self::Diarize,
            ...self::visual(),
            self::GenerateMetadata,
            self::Proxy,
        ];
    }

    /**
     * Every stage whose state counts towards the record's processing status:
     * the download of a URL import, then the pipeline.
     *
     * @return list<self>
     */
    public static function tracked(): array
    {
        return [self::Import, ...self::active()];
    }

    /**
     * Stages whose results this stage reads; it can only run once they are
     * done. Mirrors DEPENDS_ON in the AI service (app/schemas/pipeline.py).
     * Metadata reads whatever exists (transcript, frames or both), so it has
     * no hard dependency.
     *
     * @return list<self>
     */
    public function dependsOn(): array
    {
        return match ($this) {
            self::ExtractAudio, self::Scenes, self::Proxy => [self::Probe],
            self::Transcribe => [self::ExtractAudio],
            self::Diarize => [self::Transcribe],
            self::Frames => [self::Probe, self::Scenes],
            self::Vision, self::Ocr, self::Objects => [self::Frames],
            default => [],
        };
    }

    /**
     * The stages a retry of this one runs: itself and every stage that reads
     * its results, directly or through another (metadata reads the
     * transcript, the descriptions and the on-screen text). In display order.
     *
     * @return list<self>
     */
    public function withDependents(): array
    {
        $plan = [$this];

        foreach (self::active() as $stage) {
            $reads = [...$stage->dependsOn(), ...($stage === self::GenerateMetadata ? [self::Transcribe, self::Vision, self::Ocr] : [])];

            if ($stage !== $this && array_intersect(array_map(fn (self $read): string => $read->value, $reads), array_map(fn (self $planned): string => $planned->value, $plan)) !== []) {
                $plan[] = $stage;
            }
        }

        return array_values(array_filter(self::active(), fn (self $stage): bool => in_array($stage, $plan, true)));
    }

    /**
     * Visual analysis (Phase 5); switched on or off per upload.
     *
     * @return list<self>
     */
    public static function visual(): array
    {
        return [self::Scenes, self::Frames, self::Vision, self::Ocr, self::Objects];
    }

    public function label(): string
    {
        return match ($this) {
            self::Import => 'Download from URL',
            self::Probe => 'Technical metadata',
            self::Checksum => 'Checksums',
            self::ExtractAudio => 'Audio extraction',
            self::Transcribe => 'Transcription',
            self::Diarize => 'Speakers',
            self::Proxy => 'Access copy',
            self::Scenes => 'Scenes',
            self::Frames => 'Key frames',
            self::Vision => 'Frame descriptions',
            self::Ocr => 'On-screen text',
            self::Objects => 'Objects',
            self::GenerateMetadata => 'AI metadata',
        };
    }
}
