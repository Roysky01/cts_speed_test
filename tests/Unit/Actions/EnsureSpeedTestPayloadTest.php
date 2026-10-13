<?php

namespace Tests\Unit\Actions;

use App\Actions\EnsureSpeedTestPayload;
use PHPUnit\Framework\TestCase;

class EnsureSpeedTestPayloadTest extends TestCase
{
    public function test_writes_an_incompressible_payload_of_the_requested_size(): void
    {
        $path = sys_get_temp_dir().'/speedtest-payload-'.uniqid('', true).'.bin';
        $payload = new EnsureSpeedTestPayload($path, 4096);

        try {
            $this->assertSame($path, $payload->path());
            $contents = file_get_contents($path);

            $this->assertSame(4096, strlen($contents));
            $this->assertGreaterThan(strlen($contents) * 0.95, strlen(gzencode($contents, 9)));
        } finally {
            $this->deletePayloadFiles($path);
        }
    }

    public function test_reuses_an_existing_payload_of_the_same_size(): void
    {
        $path = sys_get_temp_dir().'/speedtest-payload-'.uniqid('', true).'.bin';
        $original = random_bytes(2048);
        file_put_contents($path, $original);
        $payload = new EnsureSpeedTestPayload($path, 2048);

        try {
            $payload->path();

            $this->assertSame($original, file_get_contents($path));
        } finally {
            $this->deletePayloadFiles($path);
        }
    }

    public function test_clamps_payload_size_to_the_supported_range(): void
    {
        $small = new EnsureSpeedTestPayload(sys_get_temp_dir().'/unused-small.bin', 10);
        $large = new EnsureSpeedTestPayload(sys_get_temp_dir().'/unused-large.bin', 999_999_999);

        $this->assertSame(1024, $small->expectedBytes());
        $this->assertSame(64 * 1024 * 1024, $large->expectedBytes());
    }

    private function deletePayloadFiles(string $path): void
    {
        foreach ([$path, $path.'.lock', $path.'.tmp'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
