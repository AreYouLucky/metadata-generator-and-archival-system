<?php

namespace App\Http\Controllers\Encoder;

use App\Enums\AuditAction;
use App\Enums\MetadataSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Encoder\StoreSubjectRequest;
use App\Http\Requests\Encoder\UpdateSubjectRequest;
use App\Models\Record;
use App\Models\RecordSubject;
use App\Services\Records\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Subjects (people, organisations, places, topics, events). Suggestions from
 * the AI stay unconfirmed until an encoder confirms them; rejecting one
 * deletes it. Only confirmed subjects are ever public.
 */
class SubjectController extends Controller
{
    public function store(StoreSubjectRequest $request, Record $record, AuditTrail $audit): JsonResponse
    {
        $subject = new RecordSubject($request->validated());
        $subject->source = MetadataSource::Manual;
        $subject->is_confirmed = true;
        $record->subjects()->save($subject);

        $audit->log($record, AuditAction::Updated, $request->user(), "Subject added: {$subject->subject}");

        return response()->json(['subject' => self::present($subject)], 201);
    }

    public function update(UpdateSubjectRequest $request, Record $record, RecordSubject $subject, AuditTrail $audit): JsonResponse
    {
        $subject->fill($request->safe()->only(['subject', 'type']));

        if ($request->has('is_confirmed')) {
            $subject->is_confirmed = $request->boolean('is_confirmed');
        }

        if ($subject->isDirty()) {
            $subject->save();
            $audit->log($record, AuditAction::Updated, $request->user(), ($subject->wasChanged('is_confirmed') && $subject->is_confirmed ? 'Subject confirmed: ' : 'Subject changed: ').$subject->subject);
        }

        return response()->json(['subject' => self::present($subject)]);
    }

    public function destroy(Record $record, RecordSubject $subject, AuditTrail $audit): JsonResponse
    {
        Gate::authorize('update', $record);

        $subject->delete();
        $audit->log($record, AuditAction::Updated, request()->user(), ($subject->is_confirmed ? 'Subject removed: ' : 'Suggested subject rejected: ').$subject->subject);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{id: int, subject: string, type: string, source: string, is_confirmed: bool}
     */
    public static function present(RecordSubject $subject): array
    {
        return [
            'id' => $subject->id,
            'subject' => $subject->subject,
            'type' => $subject->type->value,
            'source' => $subject->source->value,
            'is_confirmed' => $subject->is_confirmed,
        ];
    }
}
