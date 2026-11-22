<?php

namespace App\Http\Controllers\FunctionTools;

use App\Enums\SceneKind;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Models\RecordScene;
use App\Services\Chat\ChatScope;
use App\Services\Records\VisualsPresenter;

/**
 * What is SHOWN: key frames (description, on-screen text, objects, face
 * counts only, never identities) and named scenes, optionally in a time
 * range.
 */
class GetVisualContentTool extends RecordTool
{
    private const int MAX_FRAMES = 30;

    private const int MAX_SCENES = 30;

    public function name(): string
    {
        return 'get_visual_content';
    }

    public function description(): string
    {
        return 'What is SHOWN in the video: key frames with a description, on-screen text (OCR: slides, '
            .'banners, name captions) and detected objects with the number of faces, plus named scenes. '
            .'Optionally limited to a time range. Faces are only counted; nobody is identified from a face.';
    }

    protected function properties(): array
    {
        return [
            'start_seconds' => ['type' => ['number', 'null'], 'description' => 'From this time (null: the start).'],
            'end_seconds' => ['type' => ['number', 'null'], 'description' => 'Up to this time (null: the end).'],
        ];
    }

    protected function recordRules(): array
    {
        return [
            'start_seconds' => ['nullable', 'numeric', 'min:0', 'max:172800'],
            'end_seconds' => ['nullable', 'numeric', 'min:0', 'max:172800'],
        ];
    }

    protected function handleRecord(Record $record, bool $internal, array $arguments, ChatScope $scope): ToolResult
    {
        $start = isset($arguments['start_seconds']) ? (float) $arguments['start_seconds'] : null;
        $end = isset($arguments['end_seconds']) ? (float) $arguments['end_seconds'] : null;

        $frameQuery = $record->frames()
            ->when($start !== null, fn ($query) => $query->where('time_sec', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('time_sec', '<=', $end))
            ->where(fn ($query) => $query->whereNotNull('description')->orWhereNotNull('ocr_text')->orWhereNotNull('objects'));
        $total = (clone $frameQuery)->count();
        $frames = $frameQuery->limit(self::MAX_FRAMES)->get(['id', 'record_id', 'time_sec', 'description', 'ocr_text', 'objects', 'face_count']);

        $scenes = $record->scenes()
            ->where('kind', SceneKind::Scene)
            ->when($start !== null, fn ($query) => $query->where('end', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('start', '<=', $end))
            ->limit(self::MAX_SCENES)
            ->get(['id', 'record_id', 'start', 'end', 'label', 'description']);

        if ($frames->isEmpty() && $scenes->isEmpty()) {
            return ToolResult::unavailable($record->frames()->exists()
                ? 'No analysed key frames or scenes in this time range.'
                : 'Visual analysis has not been run for this record (no key frames).');
        }

        $moments = [
            ...$frames->map(fn (RecordFrame $frame): array => ['start' => $frame->time_sec, 'end' => $frame->time_sec + 5.0])->all(),
            ...$scenes->map(fn (RecordScene $scene): array => ['start' => $scene->start, 'end' => $scene->end])->all(),
        ];

        return ToolResult::found([
            'key_frames' => $frames->map(fn (RecordFrame $frame): array => array_filter([
                'start' => $frame->time_sec,
                'time' => VisualsPresenter::clock($frame->time_sec),
                'description' => $frame->description,
                'on_screen_text' => $frame->ocr_text,
                'objects' => array_values(array_unique(array_map(fn (array $item): string => $item['label'], $frame->objects ?? []))),
                // Counts only, and never on the public site.
                'faces' => $internal ? $frame->face_count : null,
            ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''))->values()->all(),
            'more_key_frames' => max(0, $total - $frames->count()),
            'scenes' => $scenes->map(fn (RecordScene $scene): array => [
                'start' => $scene->start,
                'end' => $scene->end,
                'time' => VisualsPresenter::clock($scene->start).'–'.VisualsPresenter::clock($scene->end),
                'label' => $scene->label,
                'description' => $scene->description,
            ])->values()->all(),
        ], $moments);
    }
}
