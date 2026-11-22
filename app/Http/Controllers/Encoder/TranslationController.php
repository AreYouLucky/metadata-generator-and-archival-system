<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\ShowTranslationRequest;
use App\Http\Requests\Encoder\StoreTranslationRequest;
use App\Jobs\StartTranslationJob;
use App\Models\Record;
use App\Models\RecordTranscript;
use App\Services\Records\TranslationRecorder;
use Illuminate\Http\JsonResponse;

class TranslationController extends Controller
{
    /**
     * Translate the transcript into a language, or translate it again (after
     * the original was edited, or a failure). Runs in the background; progress
     * arrives over Reverb (`record.translation-updated`).
     */
    public function store(StoreTranslationRequest $request, Record $record, TranslationRecorder $translations): JsonResponse
    {
        abort_if($record->originalTranscript()->doesntExist(), 422, 'The record has no transcript to translate yet.');

        $language = (string) $request->validated('language');
        $existing = $record->transcripts()->where('language', $language)->first();

        abort_if($existing?->isBusy() ?? false, 409, 'That translation is already running.');

        $translation = $translations->queue($record, $language);
        StartTranslationJob::dispatch($record, $translation);

        return response()->json(['translation' => self::present($translation)], 202);
    }

    /**
     * One finished translation's lines, for the viewer (where they can be
     * corrected through PATCH /records/{record}/segments/{segment}).
     */
    public function show(ShowTranslationRequest $request, Record $record): JsonResponse
    {
        $translation = $record->transcripts()
            ->where('is_original', false)
            ->where('language', $request->validated('language'))
            ->where('status', TaskStatus::Done)
            ->firstOrFail(['id', 'record_id', 'language']);

        return response()->json([
            'language' => $translation->language,
            'segments' => $translation->segments()->toBase()->get(['id', 'start', 'end', 'text', 'is_edited'])->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'start' => (float) $row->start,
                'end' => (float) $row->end,
                'text' => (string) $row->text,
                'is_edited' => (bool) $row->is_edited,
            ])->all(),
        ]);
    }

    /**
     * The record's translations for the workspace, by language.
     *
     * @return list<array{id: int, language: string, status: string, progress: int, error: string|null, line_count: int, edited_count: int}>
     */
    public static function list(Record $record): array
    {
        return array_values($record->transcripts()
            ->where('is_original', false)
            ->withCount(['segments', 'segments as edited_count' => fn ($query) => $query->where('is_edited', true)])
            ->orderBy('language')
            ->get(['id', 'record_id', 'language', 'status', 'progress', 'error'])
            ->map(fn (RecordTranscript $translation): array => self::present($translation))
            ->all());
    }

    /**
     * `edited_count`: lines corrected by hand, which translating again would replace.
     *
     * @return array{id: int, language: string, status: string, progress: int, error: string|null, line_count: int, edited_count: int}
     */
    public static function present(RecordTranscript $translation): array
    {
        /** @var int|null $count */
        $count = $translation->getAttribute('segments_count');
        /** @var int|null $edited */
        $edited = $translation->getAttribute('edited_count');
        $done = $translation->status === TaskStatus::Done;

        return [
            'id' => $translation->id,
            'language' => $translation->language,
            'status' => $translation->status->value,
            'progress' => $translation->progress,
            'error' => $translation->error,
            'line_count' => $count ?? ($done ? $translation->segments()->count() : 0),
            'edited_count' => $edited ?? ($done ? $translation->segments()->where('is_edited', true)->count() : 0),
        ];
    }
}
