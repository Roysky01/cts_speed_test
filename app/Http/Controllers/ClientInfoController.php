<?php

namespace App\Http\Controllers;

use App\Actions\ResolveClientIp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientInfoController extends Controller
{
    public function show(Request $request, ResolveClientIp $resolveClientIp): JsonResponse
    {
        $ip = $resolveClientIp($request);
        $remoteAddress = (string) $request->server('REMOTE_ADDR');

        return response()->json([
            'ip' => $ip,
            'remote_addr' => $remoteAddress,
            'proxied' => $ip !== $remoteAddress,
            'user_agent' => (string) $request->userAgent(),
            'server_name' => (string) config('speedtest.server_name'),
            'secure' => $request->isSecure(),
        ], 200, $this->noStoreHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];
    }
}
