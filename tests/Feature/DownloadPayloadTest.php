<?php

namespace Tests\Feature;

use Tests\TestCase;

class DownloadPayloadTest extends TestCase
{
    public function test_streams_the_requested_number_of_incompressible_bytes(): void
    {
        $response = $this->get(route('speedtest.download', ['bytes' => 5000]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $response->assertHeader('Content-Encoding', 'identity');
        $this->assertFalse($response->headers->has('set-cookie'));

        $content = $response->streamedContent();
        $payload = file_get_contents(storage_path('app/private/speedtest-payload.bin'));

        $this->assertSame(5000, strlen($content));
        $this->assertSame($payload, substr($content, 0, strlen($payload)));
        $this->assertSame(substr($payload, 0, 5000 - strlen($payload)), substr($content, strlen($payload)));
    }

    public function test_rejects_a_download_smaller_than_1024_bytes(): void
    {
        $response = $this->getJson(route('speedtest.download', ['bytes' => 100]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'bytes' => 'The bytes field must be at least 1024.',
        ]);
    }
}
