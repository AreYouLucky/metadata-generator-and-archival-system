<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads Philippine regions, provinces, cities/municipalities and barangays
 * from database/data/updated_phil.sql (a MySQL dump) into the app's tables,
 * keeping the original IDs. Safe to run more than once.
 */
class LocationSeeder extends Seeder
{
    /**
     * Dump table => [app table, app columns in dump column order].
     *
     * @var array<string, array{string, list<string>}>
     */
    private const TABLES = [
        'table_region' => ['regions', ['id', 'name', 'description']],
        'table_province' => ['provinces', ['id', 'region_id', 'name']],
        'table_municipality' => ['cities', ['id', 'province_id', 'name']],
        'table_barangay' => ['barangays', ['id', 'city_id', 'name']],
    ];

    private const CHUNK_SIZE = 1000;

    public function run(): void
    {
        $path = database_path('data/updated_phil.sql');
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("Cannot read the location dump at {$path}.");
        }

        foreach (self::TABLES as $dumpTable => [$table, $columns]) {
            $rows = array_map(
                fn (array $values): array => array_combine($columns, $values),
                self::parseRows($sql, $dumpTable),
            );

            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                DB::table($table)->upsert($chunk, ['id'], array_slice($columns, 1));
            }

            $this->resetSequence($table);
            $this->command?->info(sprintf('%s: %d rows', $table, count($rows)));
        }
    }

    /**
     * Extract the value tuples of one table's INSERT statement from a MySQL dump.
     * Integers become ints, NULL becomes null, and quoted strings are unescaped.
     *
     * @return list<list<int|string|null>>
     */
    public static function parseRows(string $sql, string $dumpTable): array
    {
        // The statements are about a megabyte long, too long for a single regex
        // (PCRE's backtracking limit), so find their bounds with string searches.
        $header = preg_match(sprintf('/insert\s+into\s+`%s`\s*\([^)]*\)\s*values/i', preg_quote($dumpTable, '/')), $sql, $match, PREG_OFFSET_CAPTURE);
        $start = $header === 1 ? $match[0][1] + strlen($match[0][0]) : false;
        $end = $start === false ? false : strpos($sql, ');', $start);

        if ($start === false || $end === false) {
            throw new RuntimeException("No INSERT statement found for {$dumpTable}.");
        }

        $value = "(-?\d+|NULL|'(?:[^'\\\\]|\\\\.)*')";
        preg_match_all("/\(\s*{$value}(?:\s*,\s*{$value})*\s*\)/", substr($sql, $start, $end - $start + 1), $tuples);

        $rows = [];

        foreach ($tuples[0] as $tuple) {
            preg_match_all("/{$value}/s", $tuple, $values);
            $rows[] = array_map(self::castValue(...), $values[1]);
        }

        return $rows;
    }

    private static function castValue(string $raw): int|string|null
    {
        if (strcasecmp($raw, 'NULL') === 0) {
            return null;
        }

        if ($raw[0] === "'") {
            return stripcslashes(substr($raw, 1, -1));
        }

        return (int) $raw;
    }

    /**
     * Explicit IDs don't advance PostgreSQL sequences, so move each sequence past
     * the highest seeded ID to keep later inserts from colliding.
     */
    private function resetSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1))",
            [$table],
        );
    }
}
