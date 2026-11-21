<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Enums\MetadataSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreKeywordRequest;
use App\Http\Requests\Encoder\UpdateKeywordRequest;
use App\Models\Record;
use App\Models\RecordKeyword;
use App\Services\Records\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Archive keywords. AI suggestions stay unconfirmed until confirmed;
 * rejecting one deletes it. Only confirmed keywords are ever public.
 */
class KeywordController extends Controller
{
    public function store(StoreKeywordRequest $request, Record $record, AuditTrail $audit): JsonResponse
    {
        $keyword = new RecordKeyword($request->validated());
        $keyword->source = MetadataSource::Manual;
        $keyword->is_confirmed = true;
        $record->keywords()->save($keyword);

        $audit->log($record, AuditAction::Updated, $request->user(), "Keyword added: {$keyword->keyword}");

        return response()->json(['keyword' => self::present($keyword)], 201);
    }

    public function update(UpdateKeywordRequest $request, Record $record, RecordKeyword $keyword, AuditTrail $audit): JsonResponse
    {
        $keyword->fill($request->safe()->only(['keyword']));

        if ($request->has('is_confirmed')) {
            $keyword->is_confirmed = $request->boolean('is_confirmed');
        }

        if ($keyword->isDirty()) {
            $keyword->save();
            $audit->log($record, AuditAction::Updated, $request->user(), ($keyword->wasChanged('is_confirmed') && $keyword->is_confirmed ? 'Keyword confirmed: ' : 'Keyword changed: ').$keyword->keyword);
        }

        return response()->json(['keyword' => self::present($keyword)]);
    }

    public function destroy(Record $record, RecordKeyword $keyword, AuditTrail $audit): JsonResponse
    {
        Gate::authorize('update', $record);

        $keyword->delete();
        $audit->log($record, AuditAction::Updated, request()->user(), ($keyword->is_confirmed ? 'Keyword removed: ' : 'Suggested keyword rejected: ').$keyword->keyword);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{id: int, keyword: string, source: string, is_confirmed: bool}
     */
    public static function present(RecordKeyword $keyword): array
    {
        return [
            'id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'source' => $keyword->source->value,
            'is_confirmed' => $keyword->is_confirmed,
        ];
    }
}
