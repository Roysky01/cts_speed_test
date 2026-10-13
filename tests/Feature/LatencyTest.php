<?php

namespace Tests\Feature;

use Tests\TestCase;

class LatencyTest extends TestCase
{
    public function test_returns_a_tiny_uncached_response(): void
    {
        $response = $this->getJson(route('speedtest.latency'));

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $response->assertJsonPath('ok', true);
    }
}
