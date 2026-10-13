<?php

namespace App\Actions;

class EnsureSpeedTestPayload
{
    public function __construct(
        private ?string $path = null,
        private ?int $bytes = null,
    ) {}

    public function expectedBytes(): int
    {
        $configured = $this->bytes ?? (int) config('speedtest.payload_bytes');

        return max(1024, min($configured, 64 * 1024 * 1024));
    }

    public function path(): string
    {
        $path = $this->location();
        $this->write($path, force: false);

        return $path;
    }

    public function forceGenerate(): string
    {
        $path = $this->location();
        $this->write($path, force: true);

        return $path;
    }

    private function location(): string
    {
        return $this->path ?? storage_path('app/private/speedtest-payload.bin');
    }

    private function write(string $path, bool $force): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $lockPath = $path.'.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new \RuntimeException('Unable to lock the speed test payload.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock the speed test payload.');
            }

            $expected = $this->expectedBytes();

            if (! $force && is_file($path) && filesize($path) === $expected) {
                return;
            }

            $temporary = $path.'.tmp';
            $output = fopen($temporary, 'wb');

            if ($output === false) {
                throw new \RuntimeException('Unable to write the speed test payload.');
            }

            $remaining = $expected;

            try {
                while ($remaining > 0) {
                    $size = min(1024 * 1024, $remaining);
                    $written = fwrite($output, random_bytes($size));

                    if ($written === false || $written !== $size) {
                        throw new \RuntimeException('Unable to write the speed test payload.');
                    }

                    $remaining -= $size;
                }
            } finally {
                fclose($output);
            }

            if (! rename($temporary, $path)) {
                @unlink($temporary);

                throw new \RuntimeException('Unable to publish the speed test payload.');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
