<?php

namespace Database\Seeders;

use App\Models\SpeedTest;
use Illuminate\Database\Seeder;

class SpeedTestSeeder extends Seeder
{
    /**
     * Seed sample history for the loopback address used by local browsers.
     */
    public function run(): void
    {
        SpeedTest::factory()->count(5)->create([
            'ip_address' => '127.0.0.1',
        ]);
    }
}
