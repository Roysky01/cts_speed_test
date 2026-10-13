<?php

namespace App\Http\Controllers;

use App\Actions\EnsureSpeedTestPayload;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPayloadController extends Controller
{
    public function show(Request $request, EnsureSpeedTestPayload $payload): StreamedResponse
    {
        $maxBytes = (int) config('speedtest.max_download_bytes');

        $validated = $request->validate([
            'bytes' => ['sometimes', 'integer', 'min:1024', 'max:'.$maxBytes],
        ]);

        $bytes = (int) ($validated['bytes'] ?? min($payload->expectedBytes(), $maxBytes));
        $path = $payload->path();
        $maxSeconds = max(1, (int) config('speedtest.max_stream_seconds'));

        return response()->stream(function () use ($path, $bytes, $maxSeconds): void {
            set_time_limit($maxSeconds + 10);
            ignore_user_abort(false);

            $input = fopen($path, 'rb');

            if ($input === false) {
                return;
            }

            $sent = 0;
            $sinceFlush = 0;
            $deadline = microtime(true) + $maxSeconds;

            try {
                while ($sent < $bytes && microtime(true) < $deadline && ! connection_aborted()) {
                    if (feof($input)) {
                        rewind($input);
                    }

                    $chunk = fread($input, (int) min(65536, $bytes - $sent));

                    if ($chunk === false || $chunk === '') {
                        rewind($input);

                        continue;
                    }

                    echo $chunk;
                    $length = strlen($chunk);
                    $sent += $length;
                    $sinceFlush += $length;

                    if ($sinceFlush >= 262144) {
                        $this->flushOutput();
                        $sinceFlush = 0;
                    }
                }
            } finally {
                fclose($input);
            }

            $this->flushOutput();
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Content-Encoding' => 'identity',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
