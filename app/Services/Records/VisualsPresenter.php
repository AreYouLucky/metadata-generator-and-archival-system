<?php

namespace App\Services\Records;

use App\Enums\ReleaseConsent;
use App\Enums\SceneKind;
use App\Models\Record;
use App\Models\RecordFrame;
use App\Models\RecordScene;

/**
 * The workspace's Visuals data (PROJECT_SPEC §9, VisualsPanel): key frames
 * with their analysis, detected shots, named scenes and the thumbnail, in the
 * shape of resources/js/types/visuals.ts. Two queries, columns chosen.
 */
class VisualsPresenter
{
    /** Frames with faces shown to the reviewer. */
    private const int FACE_FRAMES = 12;

    public function __construct(private RecordThumbnails $thumbnails) {}

    /**
     * @return array{frames: list<array<string, mixed>>, shots: list<array<string, mixed>>, scenes: list<array<string, mixed>>, thumbnail: array{url: string, frame_id: int|null, time_sec: float|null}|null}
     */
    public function present(Record $record): array
    {
        $segments = $record->scenes()->get(['id', 'record_id', 'kind', 'start', 'end', 'tc_in', 'tc_out', 'label', 'description']);

        return [
            'frames' => array_values($record->frames()
                ->get(['id', 'record_id', 'time_sec', 'is_scene_start', 'description', 'ocr_text', 'objects', 'face_count'])
                ->map(fn (RecordFrame $frame): array => [
                    'id' => $frame->id,
                    'time_sec' => $frame->time_sec,
                    'url' => route('records.frames.image', ['record' => $record, 'frame' => $frame]),
                    'is_scene_start' => $frame->is_scene_start,
                    'description' => $frame->description,
                    'ocr_text' => $frame->ocr_text,
                    'objects' => $frame->objects,
                    'face_count' => $frame->face_count,
                ])
                ->all()),
            'shots' => array_values($segments
                ->where('kind', SceneKind::Shot)
                ->map(fn (RecordScene $shot): array => [
                    'id' => $shot->id,
                    'start' => $shot->start,
                    'end' => $shot->end,
                    'tc_in' => $shot->tc_in,
                    'tc_out' => $shot->tc_out,
                ])
                ->all()),
            'scenes' => array_values($segments
                ->where('kind', SceneKind::Scene)
                ->map(fn (RecordScene $scene): array => self::scene($scene))
                ->all()),
            'thumbnail' => $this->thumbnails->present($record),
        ];
    }

    /**
     * Who is on screen, for the privacy check before approval: how many key
     * frames had faces counted and the first of them, with whether release
     * forms are on file. Null when faces were never counted (the `objects`
     * stage was off or hasn't run). Counts only; nobody is identified.
     *
     * @return array{counted_frames: int, frames_with_faces: int, frames: list<array{id: int, time_sec: float, face_count: int, url: string}>, release_consent: string}|null
     */
    public function faces(Record $record): ?array
    {
        $totals = RecordFrame::query()
            ->where('record_id', $record->id)
            ->whereNotNull('face_count')
            ->toBase()
            ->selectRaw('count(*) as counted, count(case when face_count > 0 then 1 end) as with_faces')
            ->first();

        $counted = (int) ($totals->counted ?? 0);

        if ($counted === 0) {
            return null;
        }

        $record->loadMissing('rights');

        return [
            'counted_frames' => $counted,
            'frames_with_faces' => (int) $totals->with_faces,
            'frames' => array_values($record->frames()
                ->where('face_count', '>', 0)
                ->limit(self::FACE_FRAMES)
                ->get(['id', 'record_id', 'time_sec', 'face_count'])
                ->map(fn (RecordFrame $frame): array => [
                    'id' => $frame->id,
                    'time_sec' => $frame->time_sec,
                    'face_count' => (int) $frame->face_count,
                    'url' => route('records.frames.image', ['record' => $record, 'frame' => $frame]),
                ])
                ->all()),
            // No rights row means the defaults, which have no release forms.
            'release_consent' => ($record->rights->release_consent ?? ReleaseConsent::NotAvailable)->value,
        ];
    }

    /**
     * @return array{id: int, start: float, end: float, tc_in: string|null, tc_out: string|null, label: string|null, description: string|null}
     */
    public static function scene(RecordScene $scene): array
    {
        return [
            'id' => $scene->id,
            'start' => $scene->start,
            'end' => $scene->end,
            'tc_in' => $scene->tc_in,
            'tc_out' => $scene->tc_out,
            'label' => $scene->label,
            'description' => $scene->description,
        ];
    }
}
