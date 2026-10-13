<?php

namespace App\Http\Controllers;

use App\Actions\ResolveClientIp;
use App\Models\SpeedTest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SpeedTestController extends Controller
{
    public function index(Request $request, ResolveClientIp $resolveClientIp): Response
    {
        $ip = $resolveClientIp($request);

        return response()->view('speedtest', [
            'ip' => $ip,
            'remoteAddress' => (string) $request->server('REMOTE_ADDR'),
            'userAgent' => (string) $request->userAgent(),
            'serverName' => (string) config('speedtest.server_name'),
            'secure' => $request->isSecure(),
            'history' => SpeedTest::query()
                ->where('ip_address', $ip)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(SpeedTest::HISTORY_LIMIT)
                ->get(),
            'config' => [
                'clientUrl' => route('speedtest.client'),
                'latencyUrl' => route('speedtest.latency'),
                'downloadUrl' => route('speedtest.download'),
                'uploadUrl' => route('speedtest.upload'),
                'resultsUrl' => route('speedtest.results.store'),
                'historyUrl' => route('speedtest.results.index'),
                'maxDownloadBytes' => (int) config('speedtest.max_download_bytes'),
                'maxUploadBytes' => (int) config('speedtest.max_upload_bytes'),
                'uploadChunkBytes' => (int) config('speedtest.upload_chunk_bytes'),
                'serverName' => (string) config('speedtest.server_name'),
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
