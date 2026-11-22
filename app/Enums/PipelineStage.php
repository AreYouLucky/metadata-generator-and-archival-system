<?php

namespace App\Enums;

/**
 * Processing steps run by the AI service.
 */
enum PipelineStage: string
{
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
            ...self::visual(),
            self::GenerateMetadata,
            self::Proxy,
        ];
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
