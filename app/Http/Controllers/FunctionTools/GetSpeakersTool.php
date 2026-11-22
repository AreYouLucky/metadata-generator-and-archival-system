<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\Record;
use App\Models\RecordSegment;
use App\Services\Chat\ChatScope;
use App\Services\Records\VisualsPresenter;

/**
 * Who speaks (labels and the names the encoder gave them), how much and
 * when they first speak, from the transcript's speaker labels.
 */
class GetSpeakersTool extends RecordTool
{
    public function name(): string
    {
        return 'get_speakers';
    }

    public function description(): string
    {
        return 'The speakers in the transcript: label (S1, S2...), the name the encoder gave them if any, '
            .'how many lines and seconds they speak, and when they first speak. Use it for "who spoke", '
            .'"who talked the most" or before searching what a named person said.';
    }

    protected function properties(): array
    {
        return [];
    }

    protected function recordRules(): array
    {
        return [];
    }

    protected function handleRecord(Record $record, bool $internal, array $arguments, ChatScope $scope): ToolResult
    {
        $speakers = $record->speakers()->orderBy('id')->get(['id', 'label', 'display_name']);
        $transcript = $record->originalTranscript()->first(['id', 'record_id']);

        if ($speakers->isEmpty() || $transcript === null) {
            return ToolResult::unavailable('Speakers have not been identified for this record (speaker detection was not run or found no one).');
        }

        $stats = RecordSegment::query()
            ->where('record_transcript_id', $transcript->id)
            ->whereNotNull('record_speaker_id')
            ->groupBy('record_speaker_id')
            ->selectRaw('record_speaker_id, count(*) as line_count, sum("end" - start) as seconds, min(start) as first_start')
            ->get()
            ->keyBy('record_speaker_id');

        return ToolResult::found([
            'speakers' => $speakers->map(function ($speaker) use ($stats): array {
                $stat = $stats->get($speaker->id);
                $first = $stat === null ? null : (float) $stat->getAttribute('first_start');

                return [
                    'label' => $speaker->label,
                    'name' => $speaker->display_name,
                    'lines' => (int) ($stat?->getAttribute('line_count') ?? 0),
                    'seconds_speaking' => round((float) ($stat?->getAttribute('seconds') ?? 0), 1),
                    'first_speaks_at' => $first === null ? null : VisualsPresenter::clock($first),
                ];
            })->values()->all(),
        ]);
    }
}
