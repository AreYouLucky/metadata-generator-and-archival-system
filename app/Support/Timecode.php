<?php

namespace App\Support;

/**
 * Seconds as SMPTE timecode (PROJECT_SPEC §11): HH:MM:SS:FF counted from the
 * master's start timecode at its frame rate, with ";" and dropped frame
 * numbers at 29.97 and 59.94 fps, and 25 fps when the rate is unknown. The
 * same arithmetic as `formatTimecode` in resources/js/lib/utilities.ts, so
 * stored and displayed timecodes agree.
 */
final class Timecode
{
    private const float DEFAULT_RATE = 25.0;

    public static function fromSeconds(float $seconds, ?string $frameRate, ?string $startTimecode): string
    {
        $rate = self::rate($frameRate) ?? self::DEFAULT_RATE;
        $nominal = (int) round($rate);
        $frames = (int) floor(max(0.0, $seconds) * $rate + 1e-6)
            + ($startTimecode !== null ? self::toFrames($startTimecode, $rate) : 0);
        $drop = self::isDropFrame($rate);

        if ($drop) {
            $dropped = (int) round($rate * 0.0666666);
            $perTenMinutes = (int) round($rate * 600);
            $perMinute = $nominal * 60 - $dropped;
            $tens = intdiv($frames, $perTenMinutes);
            $rest = $frames % $perTenMinutes;
            $frames += 9 * $dropped * $tens + ($rest > $dropped ? $dropped * intdiv($rest - $dropped, $perMinute) : 0);
        }

        $totalSeconds = intdiv($frames, $nominal);

        return sprintf(
            '%02d:%02d:%02d%s%02d',
            intdiv($totalSeconds, 3600) % 24,
            intdiv($totalSeconds, 60) % 60,
            $totalSeconds % 60,
            $drop ? ';' : ':',
            $frames % $nominal,
        );
    }

    /**
     * "30000/1001" or "25" as frames per second, or null.
     */
    public static function rate(?string $frameRate): ?float
    {
        if ($frameRate === null || $frameRate === '') {
            return null;
        }

        [$numerator, $denominator] = array_pad(explode('/', $frameRate, 2), 2, '1');

        if (! is_numeric($numerator) || ! is_numeric($denominator) || (float) $denominator === 0.0) {
            return null;
        }

        $rate = (float) $numerator / (float) $denominator;

        return $rate > 0 && is_finite($rate) ? $rate : null;
    }

    private static function isDropFrame(float $rate): bool
    {
        return abs($rate - 29.97) < 0.01 || abs($rate - 59.94) < 0.01;
    }

    /**
     * A timecode ("01:00:00:00", or ";" for drop-frame) as a frame count.
     */
    private static function toFrames(string $timecode, float $rate): int
    {
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})[:;.](\d{2,3})$/', $timecode, $match) !== 1) {
            return 0;
        }

        [$hours, $minutes, $seconds, $frames] = array_map('intval', array_slice($match, 1));
        $nominal = (int) round($rate);
        $total = ($hours * 3600 + $minutes * 60 + $seconds) * $nominal + $frames;

        if (! self::isDropFrame($rate)) {
            return $total;
        }

        $totalMinutes = $hours * 60 + $minutes;

        return $total - (int) round($rate * 0.0666666) * ($totalMinutes - intdiv($totalMinutes, 10));
    }
}
