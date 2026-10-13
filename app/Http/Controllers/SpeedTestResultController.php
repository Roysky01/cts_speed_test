<?php

namespace App\Http\Controllers;

use App\Actions\ResolveClientIp;
use App\Http\Requests\StoreSpeedTestRequest;
use App\Http\Resources\SpeedTestResource;
use App\Models\SpeedTest;
use App\Support\Throughput;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class SpeedTestResultController extends Controller
{
    public function index(Request $request, ResolveClientIp $resolveClientIp): AnonymousResourceCollection
    {
        $history = $this->historyFor($resolveClientIp($request));

        return SpeedTestResource::collection($history);
    }

    public function store(StoreSpeedTestRequest $request, ResolveClientIp $resolveClientIp): JsonResponse
    {
        $validated = $request->validated();
        $ip = $resolveClientIp($request);
        $userAgent = trim((string) $request->userAgent());

        $test = SpeedTest::query()->create([
            'ip_address' => $ip,
            'user_agent' => $userAgent === '' ? null : Str::limit($userAgent, 512, ''),
            'ping_ms' => round((float) $validated['ping_ms'], 2),
            'jitter_ms' => round((float) $validated['jitter_ms'], 2),
            'loaded_ping_ms' => isset($validated['loaded_ping_ms'])
                ? round((float) $validated['loaded_ping_ms'], 2)
                : null,
            'download_bytes' => (int) $validated['download_bytes'],
            'upload_bytes' => (int) $validated['upload_bytes'],
            'download_ms' => (int) $validated['download_ms'],
            'upload_ms' => (int) $validated['upload_ms'],
            'download_mbps' => round(Throughput::megabitsPerSecond(
                (int) $validated['download_bytes'],
                (float) $validated['download_ms'],
            ), 2),
            'upload_mbps' => round(Throughput::megabitsPerSecond(
                (int) $validated['upload_bytes'],
                (float) $validated['upload_ms'],
            ), 2),
        ]);

        return response()->json([
            'data' => new SpeedTestResource($test),
            'history' => SpeedTestResource::collection($this->historyFor($ip))->resolve(),
        ], 201);
    }

    /**
     * @return Collection<int, SpeedTest>
     */
    private function historyFor(string $ip): Collection
    {
        return SpeedTest::query()
            ->where('ip_address', $ip)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(SpeedTest::HISTORY_LIMIT)
            ->get();
    }
}
