<?php

namespace App\Console\Commands;

use App\Actions\EnsureSpeedTestPayload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('speedtest:payload')]
#[Description('Generate the incompressible file used by download tests')]
class GenerateSpeedTestPayload extends Command
{
    public function handle(EnsureSpeedTestPayload $payload): int
    {
        $path = $payload->forceGenerate();

        $this->components->info('Wrote '.$payload->expectedBytes().' bytes to '.$path);

        return self::SUCCESS;
    }
}
