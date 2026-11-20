<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\LocationSeeder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LocationSeederTest extends TestCase
{
    private const DUMP = <<<'SQL'
        insert  into `table_barangay`(`barangay_id`,`municipality_id`,`barangay_name`) values
        (1,1,'Adams (Pob.)'),
        (37403,1395,'Engineers\' Hill'),
        (36776,1361,'Dasmariñas');
        insert  into `table_municipality`(`municipality_id`,`province_id`,`municipality_name`) values
        (1,NULL,'Unassigned Town');
        SQL;

    public function test_parses_ids_names_with_escaped_quotes_and_accented_letters(): void
    {
        $rows = LocationSeeder::parseRows(self::DUMP, 'table_barangay');

        $this->assertSame([
            [1, 1, 'Adams (Pob.)'],
            [37403, 1395, "Engineers' Hill"],
            [36776, 1361, 'Dasmariñas'],
        ], $rows);
    }

    public function test_parses_null_as_null(): void
    {
        $rows = LocationSeeder::parseRows(self::DUMP, 'table_municipality');

        $this->assertSame([[1, null, 'Unassigned Town']], $rows);
    }

    public function test_rejects_a_dump_without_the_requested_table(): void
    {
        $this->expectException(RuntimeException::class);

        LocationSeeder::parseRows(self::DUMP, 'table_region');
    }
}
