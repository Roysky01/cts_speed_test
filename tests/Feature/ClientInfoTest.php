<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClientInfoTest extends TestCase
{
    public function test_reports_the_socket_address_when_proxies_are_not_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.44'])
            ->withHeaders([
                'X-Forwarded-For' => '198.51.100.9',
                'CF-Connecting-IP' => '203.0.113.77',
            ])
            ->getJson(route('speedtest.client'));

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $response->assertJsonPath('ip', '203.0.113.44');
        $response->assertJsonPath('proxied', false);
    }

    public function test_uses_the_forwarded_address_when_proxies_are_trusted(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
            ->withHeader('X-Forwarded-For', '203.0.113.50')
            ->getJson(route('speedtest.client'));

        $response->assertOk();
        $response->assertJsonPath('ip', '203.0.113.50');
        $response->assertJsonPath('proxied', true);
        $response->assertJsonPath('remote_addr', '198.51.100.2');
    }

    public function test_prefers_cloudflare_connecting_ip_when_proxies_are_trusted(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.50',
                'CF-Connecting-IP' => '203.0.113.77',
            ])
            ->getJson(route('speedtest.client'));

        $response->assertOk();
        $response->assertJsonPath('ip', '203.0.113.77');
    }
}
