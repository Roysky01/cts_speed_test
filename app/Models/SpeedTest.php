<?php

namespace App\Models;

use Database\Factories\SpeedTestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'ip_address',
    'user_agent',
    'ping_ms',
    'jitter_ms',
    'loaded_ping_ms',
    'download_mbps',
    'upload_mbps',
    'download_bytes',
    'upload_bytes',
    'download_ms',
    'upload_ms',
])]
class SpeedTest extends Model
{
    /** @use HasFactory<SpeedTestFactory> */
    use HasFactory;

    public const HISTORY_LIMIT = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ping_ms' => 'decimal:2',
            'jitter_ms' => 'decimal:2',
            'loaded_ping_ms' => 'decimal:2',
            'download_mbps' => 'decimal:2',
            'upload_mbps' => 'decimal:2',
            'download_bytes' => 'integer',
            'upload_bytes' => 'integer',
            'download_ms' => 'integer',
            'upload_ms' => 'integer',
        ];
    }
}
