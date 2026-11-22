<?php

namespace App\Services\Records;

use App\Models\Record;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;

/**
 * Accession numbers from ACCESSION_NUMBER_FORMAT (PROJECT_SPEC §11): the
 * tokens are {year} and {seq:N}, a system-wide sequence zero-padded to N
 * digits, e.g. "ACC-{year}-{seq:5}" -> "ACC-2026-00042". The sequence row is
 * locked while it is incremented. A number an encoder typed by hand is
 * skipped over, so generated numbers never collide with it.
 */
class AccessionNumbers
{
    public const string SEQUENCE = 'accession_number';

    public function __construct(
        #[Config('archive.accession_number_format')] private string $format = 'ACC-{year}-{seq:5}',
    ) {}

    public function next(): string
    {
        return DB::transaction(function (): string {
            do {
                $number = $this->format($this->increment());
            } while (Record::withTrashed()->where('accession_number', $number)->exists());

            return $number;
        });
    }

    public function format(int $sequence, ?int $year = null): string
    {
        $number = str_replace('{year}', (string) ($year ?? now()->year), $this->format);

        return (string) preg_replace_callback(
            '/\{seq(?::(\d{1,2}))?\}/',
            fn (array $match): string => str_pad((string) $sequence, (int) ($match[1] ?? 0), '0', STR_PAD_LEFT),
            $number,
        );
    }

    private function increment(): int
    {
        DB::table('sequences')->insertOrIgnore(['name' => self::SEQUENCE, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $value = (int) DB::table('sequences')->where('name', self::SEQUENCE)->lockForUpdate()->value('value') + 1;
        DB::table('sequences')->where('name', self::SEQUENCE)->update(['value' => $value, 'updated_at' => now()]);

        return $value;
    }
}
