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
     * The stages the pipeline runs today, in run order (checksums first, before
     * anything else reads the file). Later phases add their stages here.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Checksum, self::Probe, self::ExtractAudio, self::Transcribe, self::GenerateMetadata];
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
