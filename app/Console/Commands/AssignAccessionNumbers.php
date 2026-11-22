<?php

namespace App\Console\Commands;

use App\Models\Record;
use App\Services\Records\AccessionNumbers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Give records created before Phase 3 an accession number, oldest first, so
 * the sequence follows the order they entered the archive.
 */
#[Signature('archive:assign-accession-numbers')]
#[Description('Assign accession numbers to records that have none (oldest first)')]
class AssignAccessionNumbers extends Command
{
    public function handle(AccessionNumbers $numbers): int
    {
        $assigned = 0;

        Record::withTrashed()
            ->whereNull('accession_number')
            ->orderBy('created_at')
            ->select(['id', 'accession_number', 'created_at'])
            ->lazyById(200)
            ->each(function (Record $record) use ($numbers, &$assigned): void {
                $record->accession_number = $numbers->next();
                $record->saveQuietly();
                $assigned++;
            });

        $this->info("Assigned {$assigned} accession ".($assigned === 1 ? 'number' : 'numbers').'.');

        return self::SUCCESS;
    }
}
