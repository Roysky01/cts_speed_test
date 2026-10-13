<?php

namespace Database\Factories;

use App\Models\SpeedTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeedTest>
 */
class SpeedTestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'ping_ms' => fake()->randomFloat(2, 1, 80),
            'jitter_ms' => fake()->randomFloat(2, 0, 15),
            'loaded_ping_ms' => fake()->randomFloat(2, 1, 150),
            'download_mbps' => fake()->randomFloat(2, 1, 500),
            'upload_mbps' => fake()->randomFloat(2, 1, 200),
            'download_bytes' => fake()->numberBetween(100_000, 50_000_000),
            'upload_bytes' => fake()->numberBetween(100_000, 20_000_000),
            'download_ms' => fake()->numberBetween(1_000, 15_000),
            'upload_ms' => fake()->numberBetween(1_000, 15_000),
        ];
    }
}
