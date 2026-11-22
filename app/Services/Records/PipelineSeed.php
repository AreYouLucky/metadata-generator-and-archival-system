<?php

namespace App\Services\Records;

use App\Enums\PipelineStage;
use App\Enums\SceneKind;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Models\RecordScene;
use App\Models\RecordSegment;

/**
 * What a partial run (a retry of one stage) needs from stages it doesn't run
 * again. The AI service keeps nothing between runs, so Laravel sends the
 * stored transcript, shots and frames, and only those a rerun stage reads.
 */
class PipelineSeed
{
    /**
     * @param  list<PipelineStage>  $stages  the stages that run again
     * @return array{language?: string|null, segments?: list<array{start: float, end: float, text: string}>, shots?: list<array{start: float, end: float}>, frames?: list<array{time_sec: float, path: string, is_scene_start: bool, description: string|null, ocr_text: string|null}>}
     */
    public function for(Record $record, array $stages): array
    {
        $runs = fn (PipelineStage ...$any): bool => array_intersect(
            array_map(fn (PipelineStage $stage): string => $stage->value, $any),
            array_map(fn (PipelineStage $stage): string => $stage->value, $stages),
        ) !== [];

        $seed = [];

        if (! $runs(PipelineStage::Transcribe) && $runs(PipelineStage::Diarize, PipelineStage::GenerateMetadata)) {
            $transcript = $record->originalTranscript()->first(['id', 'record_id', 'language']);
            $seed['language'] = $transcript?->language === 'und' ? null : $transcript?->language;
            $seed['segments'] = $transcript === null ? [] : array_values($transcript->segments()
                ->get(['start', 'end', 'text'])
                ->map(fn (RecordSegment $segment): array => ['start' => (float) $segment->start, 'end' => (float) $segment->end, 'text' => $segment->text])
                ->all());
        }

        if (! $runs(PipelineStage::Scenes) && $runs(PipelineStage::Frames)) {
            $seed['shots'] = array_values($record->scenes()
                ->where('kind', SceneKind::Shot)
                ->orderBy('start')
                ->get(['start', 'end'])
                ->map(fn (RecordScene $shot): array => ['start' => (float) $shot->start, 'end' => (float) $shot->end])
                ->all());
        }

        if (! $runs(PipelineStage::Frames) && $runs(PipelineStage::Vision, PipelineStage::Ocr, PipelineStage::Objects, PipelineStage::GenerateMetadata)) {
            $seed['frames'] = array_values($record->frames()
                ->orderBy('time_sec')
                ->get(['time_sec', 'path', 'is_scene_start', 'description', 'ocr_text'])
                ->map(fn (RecordFrame $frame): array => [
                    'time_sec' => (float) $frame->time_sec,
                    'path' => $frame->path,
                    'is_scene_start' => (bool) $frame->is_scene_start,
                    'description' => $frame->description,
                    'ocr_text' => $frame->ocr_text,
                ])
                ->all());
        }

        return $seed;
    }
}
