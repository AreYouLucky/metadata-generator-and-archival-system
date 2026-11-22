<?php

namespace App\Services\Records;

use App\Enums\AuditAction;
use App\Enums\ChecksumStatus;
use App\Enums\FileRole;
use App\Enums\FixityTrigger;
use App\Enums\UserRole;
use App\Events\RecordArchiveUpdated;
use App\Jobs\RunFixityCheckJob;
use App\Models\Record;
use App\Models\RecordFile;
use App\Models\User;
use App\Notifications\FixityMismatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Fixity checks (PROJECT_SPEC §11): masters and mezzanines are re-hashed and
 * compared with the checksums taken at ingest. Each result is stored in
 * `fixity_checks` and logged; a mismatch (or a missing file) flags the file
 * and notifies the record's owner and every admin.
 */
class FixityRecorder
{
    /**
     * Files whose integrity is checked: originals that can't be regenerated.
     *
     * @var list<FileRole>
     */
    public const array CHECKED_ROLES = [FileRole::Master, FileRole::Mezzanine];

    public function __construct(private AuditTrail $audit) {}

    /**
     * Queue a check of every checked file of the record that has ingest checksums.
     *
     * @return int the number of files queued
     */
    public function queue(Record $record, FixityTrigger $trigger): int
    {
        $files = $record->files()
            ->whereIn('role', self::CHECKED_ROLES)
            ->whereNotNull('sha256_checksum')
            ->get(['id', 'record_id', 'role', 'storage_location']);

        foreach ($files as $file) {
            RunFixityCheckJob::dispatch($file, $trigger);
        }

        return $files->count();
    }

    /**
     * @param  array{md5?: string|null, sha256?: string|null, error?: string|null}  $result
     */
    public function record(RecordFile $file, FixityTrigger $trigger, array $result): bool
    {
        $record = $file->record()->withTrashed()->firstOrFail();
        $missing = ($result['error'] ?? null) === 'missing';
        $failed = ! $missing && ($result['error'] ?? null) !== null;

        if ($failed) {
            $this->audit->log($record, AuditAction::Updated, null, "Fixity check could not run: {$result['error']}", $file->id);
            rescue(fn () => RecordArchiveUpdated::dispatch($record, 'fixity'), report: false);

            return false;
        }

        $matched = DB::transaction(function () use ($file, $trigger, $result, $missing, $record): bool {
            $matched = true;

            foreach (['sha256' => $file->sha256_checksum, 'md5' => $file->md5_checksum] as $algorithm => $expected) {
                if ($expected === null) {
                    continue;
                }

                $actual = $missing ? null : ($result[$algorithm] ?? null);
                $ok = $actual !== null && hash_equals($expected, $actual);
                $matched = $matched && $ok;

                $file->fixityChecks()->create([
                    'algorithm' => $algorithm,
                    'expected' => $expected,
                    'actual' => $actual,
                    'matched' => $ok,
                    'triggered_by' => $trigger,
                    'checked_at' => now(),
                ]);
            }

            if (! $matched) {
                $file->checksum_status = ChecksumStatus::Mismatch;
                $file->save();
            }

            $this->audit->log(
                $record,
                $matched ? AuditAction::ChecksumVerified : AuditAction::ChecksumFailed,
                null,
                match (true) {
                    $missing => "Fixity check ({$trigger->value}): the {$file->role->value} file is missing from storage.",
                    $matched => "Fixity check ({$trigger->value}): checksums match.",
                    default => "Fixity check ({$trigger->value}): the {$file->role->value} file no longer matches its ingest checksums.",
                },
                $file->id,
            );

            return $matched;
        });

        if (! $matched) {
            $recipients = User::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('role', UserRole::Admin)->orWhere('id', $record->created_by))
                ->get();
            Notification::send($recipients, new FixityMismatch($record, $file, $missing));
        }

        rescue(fn () => RecordArchiveUpdated::dispatch($record, 'fixity'), report: false);

        return $matched;
    }
}
