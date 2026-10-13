<?php

namespace Tests\Feature;

use Tests\TestCase;

class UploadPayloadTest extends TestCase
{
    public function test_counts_the_uploaded_bytes(): void
    {
        $payload = random_bytes(2048);

        $response = $this->call(
            'POST',
            route('speedtest.upload'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        );

        $response->assertOk();
        $response->assertJsonPath('bytes', 2048);
    }

    public function test_rejects_an_upload_larger_than_the_server_limit(): void
    {
        $payload = random_bytes(((int) config('speedtest.max_upload_bytes')) + 1);

        $response = $this->call(
            'POST',
            route('speedtest.upload'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        );

        $response->assertStatus(413);
        $response->assertJsonPath('message', 'Upload is larger than this server accepts.');
    }
}
