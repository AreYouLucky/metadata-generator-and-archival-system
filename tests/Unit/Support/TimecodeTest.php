<?php

namespace Tests\Unit\Support;

use App\Support\Timecode;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class TimecodeTest extends TestCase
{
    #[TestWith([0.0, '25/1', null, '00:00:00:00'])]
    #[TestWith([4.2, '25/1', '01:00:00:00', '01:00:04:05'])]
    #[TestWith([3661.5, '25', null, '01:01:01:12'])]
    #[TestWith([1.0, null, null, '00:00:01:00'])] // unknown rate: 25 fps
    #[TestWith([60.0, '30000/1001', null, '00:00:59;28'])] // frame 1798
    #[TestWith([60.07, '30000/1001', null, '00:01:00;02'])] // frame 1800: ;00 and ;01 are dropped
    #[TestWith([600.0, '30000/1001', null, '00:10:00;00'])] // every tenth minute keeps them
    public function test_seconds_become_smpte_timecode(float $seconds, ?string $rate, ?string $start, string $expected): void
    {
        $this->assertSame($expected, Timecode::fromSeconds($seconds, $rate, $start));
    }

    #[TestWith(['bad'])]
    #[TestWith(['25/0'])]
    #[TestWith([''])]
    public function test_an_unusable_frame_rate_is_unknown(string $rate): void
    {
        $this->assertNull(Timecode::rate($rate));
    }
}
