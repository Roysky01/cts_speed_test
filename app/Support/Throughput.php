<?php

namespace App\Support;

class Throughput
{
    /**
     * Decimal megabits per second. One megabit is 1,000,000 bits, which is
     * the unit internet plans use.
     */
    public static function megabitsPerSecond(int $bytes, float $milliseconds): float
    {
        if ($milliseconds <= 0 || $bytes <= 0) {
            return 0.0;
        }

        return ($bytes * 8) / ($milliseconds / 1000) / 1_000_000;
    }
}
