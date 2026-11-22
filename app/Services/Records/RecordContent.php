<?php

namespace App\Services\Records;

use App\Http\Controllers\Encoder\SpeakerController;
use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;

/**
 * A record's transcript, speakers and active AI generation, in the shape the
 * frontend's types expect. Shared by the workspace and the admin review page,
 * so a reviewer sees exactly what the encoder worked on.
 */
class RecordContent
{
    /**
     * @return array{language: string, segments: list<array{id: int, start: float, end: float, text: string, is_edited: bool, speaker_id: int|null}>}|null
     */
    public function transcript(Record $record): ?array
    {
        $transcript = $record->originalTranscript()->first(['id', 'record_id', 'language']);

        if ($transcript === null) {
            return null;
        }

        return [
            'language' => $transcript->language,
            'segments' => array_values($transcript->segments()
                ->get(['id', 'start', 'end', 'text', 'is_edited', 'record_speaker_id'])
                ->map(fn (RecordSegment $segment): array => [
                    'id' => $segment->id,
                    'start' => $segment->start,
                    'end' => $segment->end,
                    'text' => $segment->text,
                    'is_edited' => $segment->is_edited,
                    'speaker_id' => $segment->record_speaker_id,
                ])
                ->all()),
        ];
    }

    /**
     * @return list<array{id: int, label: string, display_name: string|null}>
     */
    public function speakers(Record $record): array
    {
        return array_values($record->speakers()
            ->orderBy('id')
            ->get(['id', 'label', 'display_name'])
            ->map(fn (RecordSpeaker $speaker): array => SpeakerController::present($speaker))
            ->all());
    }

    /**
     * @return array{id: int, preset: string, model: string, custom_prompt: string|null, created_at: string|null, payload: array<string, mixed>}|null
     */
    public function activeMetadata(Record $record): ?array
    {
        $metadata = $record->activeMetadata()->first();

        return $metadata === null ? null : [
            'id' => $metadata->id,
            'preset' => $metadata->preset->value,
            'model' => $metadata->model,
            'custom_prompt' => $metadata->custom_prompt,
            'created_at' => $metadata->created_at?->toIso8601String(),
            // Generations from before Phase 2 have no quotes, tone or flags.
            'payload' => [
                'quotes' => [],
                'tone' => null,
                'contentFlags' => null,
                ...$metadata->payload,
            ],
        ];
    }
}
