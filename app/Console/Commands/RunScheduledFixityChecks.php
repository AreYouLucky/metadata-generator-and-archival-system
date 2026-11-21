<?php

namespace App\Console\Commands;

use App\Enums\FixityTrigger;
use App\Jobs\RunFixityCheckJob;
use App\Models\RecordFile;
use App\Services\Records\FixityRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * The scheduled fixity check (PROJECT_SPEC §11): every master and mezzanine
 * whose last check is older than FIXITY_CHECK_INTERVAL_DAYS (or that was
 * never checked since ingest) is re-hashed. Runs daily; `--limit` spreads a
 * large archive over several days so the disks aren't read all at once.
 */
#[Signature('archive:fixity {--limit=500 : Most files to queue in one run}')]
#[Description('Queue fixity checks for files not checked within the configured interval')]
class RunScheduledFixityChecks extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('archive.fixity_check_interval_days'));
        $queued = 0;

        RecordFile::query()
            ->whereIn('role', FixityRecorder::CHECKED_ROLES)
            ->whereNotNull('sha256_checksum')
            ->whereHas('record')
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('fixityChecks', fn (Builder $checks) => $checks->where('triggered_by', '!=', FixityTrigger::Ingest)->where('checked_at', '>=', $cutoff))
                ->where('created_at', '<', $cutoff))
            ->orderBy('created_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->select(['id', 'record_id', 'role', 'storage_location'])
            ->lazyById(100)
            ->each(function (RecordFile $file) use (&$queued): void {
                RunFixityCheckJob::dispatch($file, FixityTrigger::Schedule);
                $queued++;
            });

        $this->info("Queued {$queued} fixity ".($queued === 1 ? 'check' : 'checks').'.');

        return self::SUCCESS;
    }
}
