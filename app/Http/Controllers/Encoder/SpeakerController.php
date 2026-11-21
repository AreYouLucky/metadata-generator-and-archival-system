<?php

namespace App\Http\Controllers\Encoder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreSpeakerRequest;
use App\Http\Requests\Encoder\UpdateSpeakerRequest;
use App\Models\Record;
use App\Models\RecordSpeaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SpeakerController extends Controller
{
    /**
     * Add a speaker by hand. Labels follow diarization's S1, S2... so both kinds mix.
     */
    public function store(StoreSpeakerRequest $request, Record $record): JsonResponse
    {
        $speaker = DB::transaction(function () use ($request, $record): RecordSpeaker {
            // Two quick clicks must not both pick the same next label.
            Record::query()->whereKey($record->id)->lockForUpdate()->first(['id']);

            $highest = $record->speakers()
                ->pluck('label')
                ->map(fn (string $label): int => preg_match('/^S(\d+)$/', $label, $match) === 1 ? (int) $match[1] : 0)
                ->max() ?? 0;

            return $record->speakers()->create([
                'label' => 'S'.($highest + 1),
                'display_name' => self::cleanName($request->validated('display_name')),
            ]);
        });

        return response()->json(self::present($speaker), 201);
    }

    /**
     * Rename a speaker; an empty name falls back to its label.
     */
    public function update(UpdateSpeakerRequest $request, Record $record, RecordSpeaker $speaker): JsonResponse
    {
        $speaker->update(['display_name' => self::cleanName($request->validated('display_name'))]);

        return response()->json(self::present($speaker));
    }

    /**
     * @return array{id: int, label: string, display_name: string|null}
     */
    public static function present(RecordSpeaker $speaker): array
    {
        return [
            'id' => $speaker->id,
            'label' => $speaker->label,
            'display_name' => $speaker->display_name,
        ];
    }

    private static function cleanName(mixed $name): ?string
    {
        $name = trim((string) $name);

        return $name === '' ? null : $name;
    }
}
