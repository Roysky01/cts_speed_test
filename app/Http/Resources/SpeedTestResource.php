<?php

namespace App\Http\Resources;

use App\Models\SpeedTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpeedTest
 */
class SpeedTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ping_ms' => (float) $this->ping_ms,
            'jitter_ms' => (float) $this->jitter_ms,
            'loaded_ping_ms' => $this->loaded_ping_ms === null ? null : (float) $this->loaded_ping_ms,
            'download_mbps' => (float) $this->download_mbps,
            'upload_mbps' => (float) $this->upload_mbps,
            'download_bytes' => $this->download_bytes,
            'upload_bytes' => $this->upload_bytes,
            'download_ms' => $this->download_ms,
            'upload_ms' => $this->upload_ms,
            'tested_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
