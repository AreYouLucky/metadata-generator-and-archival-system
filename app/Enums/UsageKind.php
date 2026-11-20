<?php

namespace App\Enums;

/**
 * Kinds of metered usage recorded per encoder.
 */
enum UsageKind: string
{
    case TranscriptionMinutes = 'transcription_minutes';
    case LlmInputTokens = 'llm_input_tokens';
    case LlmOutputTokens = 'llm_output_tokens';
    case VisionFrames = 'vision_frames';
    case StorageBytes = 'storage_bytes';
}
