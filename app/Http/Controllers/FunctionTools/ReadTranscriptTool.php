<?php

namespace App\Http\Controllers\FunctionTools;

use App\Models\Record;
use App\Models\RecordSegment;
use App\Models\RecordSpeaker;
use App\Services\Chat\ChatScope;
use App\Services\Records\VisualsPresenter;

/**
 * The exact transcript lines in a time range (at most five minutes), e.g.
 * to read the start of the video or around a passage the search found.
 */
class ReadTranscriptTool extends RecordTool
{
    public const float MAX_RANGE_SECONDS = 300.0;

    private const int MAX_CHARS = 9000;

    public function name(): string
    {
        return 'read_transcript';
    }

    public function description(): string
    {
        return 'Read the exact transcript lines between two times (at most 300 seconds at once), with speaker '
            .'names when known. Use it for "what happens at 12:30", the opening or closing remarks, or to '
            .'read more around a passage that search_transcript found.';
    }

    protected function properties(): array
    {
        return [
            'start_seconds' => ['type' => 'number', 'description' => 'From this time, in seconds.'],
            'end_seconds' => ['type' => 'number', 'description' => 'Up to this time, in seconds (at most 300 s after the start).'],
        ];
    }

    protected function recordRules(): array
    {
        return [
            'start_seconds' => ['required', 'numeric', 'min:0', 'max:172800'],
            'end_seconds' => ['required', 'numeric', 'gt:start_seconds', 'max:172800'],
        ];
    }

    protected function handleRecord(Record $record, bool $internal, array $arguments, ChatScope $scope): ToolResult
    {
        $transcript = $record->originalTranscript()->first(['id', 'record_id']);

        if ($transcript === null) {
            return ToolResult::unavailable('This record has no transcript yet (it may still be processing, or nothing was said).');
        }

        $start = (float) $arguments['start_seconds'];
        $end = min((float) $arguments['end_seconds'], $start + self::MAX_RANGE_SECONDS);
        $names = $record->speakers()
            ->get(['id', 'label', 'display_name'])
            ->mapWithKeys(fn (RecordSpeaker $speaker): array => [$speaker->id => $speaker->display_name ?: $speaker->label])
            ->all();

        $lines = [];
        $chars = 0;
        $truncated = false;

        foreach ($transcript->segments()->where('end', '>', $start)->where('start', '<', $end)->get(['id', 'start', 'end', 'text', 'record_speaker_id']) as $segment) {
            /** @var RecordSegment $segment */
            $chars += mb_strlen($segment->text);

            if ($chars > self::MAX_CHARS) {
                $truncated = true;
                break;
            }

            $lines[] = [
                'start' => round($segment->start, 2),
                'end' => round($segment->end, 2),
                'time' => VisualsPresenter::clock($segment->start),
                'speaker' => $segment->record_speaker_id === null ? null : ($names[$segment->record_speaker_id] ?? null),
                'text' => $segment->text,
            ];
        }

        if ($lines === []) {
            return ToolResult::unavailable('Nothing was said between these times.');
        }

        $last = end($lines);

        return ToolResult::found([
            'from_seconds' => $start,
            'to_seconds' => $truncated ? $last['end'] : $end,
            'truncated' => $truncated,
            'lines' => $lines,
        ], [['start' => $lines[0]['start'], 'end' => $last['end']]]);
    }
}
