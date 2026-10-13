<?php

namespace Tests\Unit\Support;

use App\Support\Throughput;
use PHPUnit\Framework\TestCase;

class ThroughputTest extends TestCase
{
    public function test_converts_bytes_and_milliseconds_to_decimal_megabits(): void
    {
        $this->assertSame(8.0, Throughput::megabitsPerSecond(1_000_000, 1000));
        $this->assertSame(10.5, Throughput::megabitsPerSecond(1_312_500, 1000));
    }

    public function test_returns_zero_when_there_is_no_transfer(): void
    {
        $this->assertSame(0.0, Throughput::megabitsPerSecond(0, 1000));
        $this->assertSame(0.0, Throughput::megabitsPerSecond(1_000_000, 0));
    }
}
