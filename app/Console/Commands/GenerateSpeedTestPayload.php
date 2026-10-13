<?php

namespace App\Console\Commands;

use App\Actions\EnsureSpeedTestPayload;
use Illuminate\Console\Command;

class GenerateSpeedTestPayload extends Command
{
    protected $signature = 'speedtest:payload';

    protected $description = 'Generate the incompressible file used by download tests';

    public function handle(EnsureSpeedTestPayload $payload): int
    {
        $path = $payload->forceGenerate();

        $this->components->info('Wrote '.$payload->expectedBytes().' bytes to '.$path);

        return self::SUCCESS;
    }
}
