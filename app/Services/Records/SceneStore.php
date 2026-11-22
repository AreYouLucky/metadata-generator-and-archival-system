<?php

namespace App\Services\Records;

use App\Enums\SceneKind;
use App\Models\Record;
use App\Models\RecordScene;
use App\Support\Timecode;
use Illuminate\Support\Facades\DB;

/**
 * Shots (detected by the `scenes` stage) and scenes (named groups of shots
 * made by an encoder), each with timecode in and out from the master's frame
 * rate and start timecode (PROJECT_SPEC §11).
 */
class SceneStore
{
    private const int INSERT_BATCH = 500;

    /**
     * Replace the detected shots. Scenes made by encoders stay: the video
     * hasn't changed, so their times are still right.
     *
     * @param  list<array{start: float, end: float}>  $shots
     */
    public function replaceShots(Record $record, array $shots): void
    {
        [$rate, $start] = $this->timecodeBase($record);
        $now = now();

        $rows = array_map(fn (array $shot): array => [
            'record_id' => $record->id,
            'kind' => SceneKind::Shot->value,
            'start' => $shot['start'],
            'end' => $shot['end'],
            'tc_in' => Timecode::fromSeconds($shot['start'], $rate, $start),
            'tc_out' => Timecode::fromSeconds($shot['end'], $rate, $start),
            'created_at' => $now,
            'updated_at' => $now,
        ], $shots);

        DB::transaction(function () use ($record, $rows): void {
            RecordScene::query()->where('record_id', $record->id)->where('kind', SceneKind::Shot)->delete();

            foreach (array_chunk($rows, self::INSERT_BATCH) as $batch) {
                RecordScene::query()->insert($batch);
            }
        });
    }

    /**
     * @param  array{start: float, end: float, label: string, description?: string|null}  $data
     */
    public function createScene(Record $record, array $data): RecordScene
    {
        $scene = new RecordScene(['kind' => SceneKind::Scene]);
        $scene->record()->associate($record);

        return $this->save($record, $scene, $data);
    }

    /**
     * @param  array{start?: float, end?: float, label?: string, description?: string|null}  $data
     */
    public function updateScene(Record $record, RecordScene $scene, array $data): RecordScene
    {
        return $this->save($record, $scene, $data);
    }

    /**
     * @param  array{start?: float, end?: float, label?: string, description?: string|null}  $data
     */
    private function save(Record $record, RecordScene $scene, array $data): RecordScene
    {
        [$rate, $start] = $this->timecodeBase($record);

        $scene->fill($data);
        $scene->tc_in = Timecode::fromSeconds($scene->start, $rate, $start);
        $scene->tc_out = Timecode::fromSeconds($scene->end, $rate, $start);
        $scene->save();

        return $scene;
    }

    /**
     * @return array{0: string|null, 1: string|null} the master's frame rate and start timecode
     */
    private function timecodeBase(Record $record): array
    {
        $technical = $record->masterFile()->first()?->technicalMetadata()->first(['id', 'frame_rate', 'start_timecode']);

        return [$technical?->frame_rate, $technical?->start_timecode];
    }
}
