<?php

namespace Tests\Feature;

use App\Models\SpeedTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpeedTestResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_server_calculated_megabits_for_the_visitor_ip(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->withHeader('User-Agent', 'CTS-Test')
            ->postJson(route('speedtest.results.store'), [
                ...$this->validPayload(),
                'ip_address' => '198.51.100.1',
                'download_mbps' => 9999,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.download_mbps', 10.5);
        $response->assertJsonPath('data.upload_mbps', 2.5);
        $response->assertJsonPath('data.loaded_ping_ms', 30.5);
        $response->assertJsonCount(1, 'history');

        $saved = SpeedTest::query()->sole();

        $this->assertSame('203.0.113.77', $saved->ip_address);
        $this->assertSame('CTS-Test', $saved->user_agent);
        $this->assertSame('10.50', $saved->download_mbps);
        $this->assertSame('2.50', $saved->upload_mbps);
    }

    public function test_lists_only_results_for_the_requesting_ip(): void
    {
        SpeedTest::factory()->create([
            'ip_address' => '198.51.100.8',
            'download_mbps' => 4.25,
        ]);
        SpeedTest::factory()->create([
            'ip_address' => '203.0.113.77',
            'download_mbps' => 10.5,
            'created_at' => now()->subMinute(),
        ]);
        SpeedTest::factory()->create([
            'ip_address' => '203.0.113.77',
            'download_mbps' => 22.5,
            'created_at' => now(),
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->getJson(route('speedtest.results.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.download_mbps', 22.5);
        $response->assertJsonPath('data.1.download_mbps', 10.5);
        $response->assertJsonMissing(['download_mbps' => 4.25]);
    }

    public function test_empty_result_requires_the_measurement_fields(): void
    {
        $response = $this->postJson(route('speedtest.results.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'ping_ms' => 'The ping ms field is required.',
            'jitter_ms' => 'The jitter ms field is required.',
            'download_bytes' => 'The download bytes field is required.',
            'download_ms' => 'The download ms field is required.',
            'upload_bytes' => 'The upload bytes field is required.',
            'upload_ms' => 'The upload ms field is required.',
        ]);
    }

    #[DataProvider('rejectedMeasurements')]
    public function test_rejects_measurements_outside_the_accepted_range(string $field, int|float $value, string $message): void
    {
        $payload = $this->validPayload();
        $payload[$field] = $value;

        $response = $this->postJson(route('speedtest.results.store'), $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([$field => $message]);
    }

    public function test_throttles_repeated_saves_from_the_same_ip(): void
    {
        $payload = $this->validPayload();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(route('speedtest.results.store'), $payload)->assertCreated();
        }

        $this->postJson(route('speedtest.results.store'), $payload)->assertTooManyRequests();
    }

    /**
     * @return array<string, array{0: string, 1: int|float, 2: string}>
     */
    public static function rejectedMeasurements(): array
    {
        return [
            'ping above 10000' => ['ping_ms', 10001, 'The ping ms field must not be greater than 10000.'],
            'download window below 200 ms' => ['download_ms', 100, 'The download ms field must be at least 200.'],
            'negative download bytes' => ['download_bytes', -1, 'The download bytes field must be at least 0.'],
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function validPayload(): array
    {
        return [
            'ping_ms' => 18.25,
            'jitter_ms' => 1.5,
            'loaded_ping_ms' => 30.5,
            'download_bytes' => 1_312_500,
            'download_ms' => 1000,
            'upload_bytes' => 625_000,
            'upload_ms' => 2000,
        ];
    }
}
