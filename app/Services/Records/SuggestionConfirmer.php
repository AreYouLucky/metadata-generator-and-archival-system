<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Models\Record;
use App\Models\RecordKeyword;
use App\Models\RecordSubject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirms every pending subject and keyword suggestion of a record at once
 * (the encoder's "Confirm all", or the option when submitting). One audit
 * entry lists what was confirmed.
 */
class SuggestionConfirmer
{
    public function __construct(private AuditTrail $audit) {}

    public function pendingCount(Record $record): int
    {
        return $record->subjects()->where('is_confirmed', false)->count()
            + $record->keywords()->where('is_confirmed', false)->count();
    }

    /**
     * @return int how many were confirmed
     */
    public function confirmAll(Record $record, ?User $user): int
    {
        return DB::transaction(function () use ($record, $user): int {
            $subjects = $record->subjects()->where('is_confirmed', false)->pluck('subject');
            $keywords = $record->keywords()->where('is_confirmed', false)->pluck('keyword');

            if ($subjects->isEmpty() && $keywords->isEmpty()) {
                return 0;
            }

            RecordSubject::query()->where('record_id', $record->id)->where('is_confirmed', false)->update(['is_confirmed' => true]);
            RecordKeyword::query()->where('record_id', $record->id)->where('is_confirmed', false)->update(['is_confirmed' => true]);

            $this->audit->log($record, AuditAction::Updated, $user, trim(implode(' ', [
                $subjects->isEmpty() ? '' : 'Subjects confirmed: '.$subjects->implode(', ').'.',
                $keywords->isEmpty() ? '' : 'Keywords confirmed: '.$keywords->implode(', ').'.',
            ])));

            return $subjects->count() + $keywords->count();
        });
    }
}
