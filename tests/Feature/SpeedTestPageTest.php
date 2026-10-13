<?php

namespace Tests\Feature;

use App\Models\SpeedTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeedTestPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_the_visitor_ip_and_only_their_history(): void
    {
        SpeedTest::factory()->create([
            'ip_address' => '198.51.100.8',
            'download_mbps' => 12.25,
        ]);
        SpeedTest::factory()->create([
            'ip_address' => '203.0.113.10',
            'ping_ms' => 17.5,
            'download_mbps' => 87.65,
            'upload_mbps' => 21.25,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('speedtest.show'));

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $response->assertSee('203.0.113.10');
        $response->assertSee('Start test');
        $response->assertSee('87.65');
        $response->assertSee('21.25');
        $response->assertDontSee('198.51.100.8');
        $response->assertDontSee('12.25');
    }
}
