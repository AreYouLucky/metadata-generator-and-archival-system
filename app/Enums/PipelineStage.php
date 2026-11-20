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
}
