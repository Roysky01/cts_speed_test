<?php

namespace App\Actions;

use Illuminate\Http\Request;

class ResolveClientIp
{
    public function __invoke(Request $request): string
    {
        $cloudflareIp = $request->headers->get('CF-Connecting-IP');

        if ($this->cloudflareHeaderIsTrusted() && is_string($cloudflareIp) && filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }

        $ip = $request->ip();

        if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }

    public function cloudflareHeaderIsTrusted(): bool
    {
        $trusted = config('trustedproxy.proxies');

        if (is_string($trusted)) {
            return trim($trusted) !== '';
        }

        return is_array($trusted) && $trusted !== [];
    }
}
