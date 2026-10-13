<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class LatencyController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'ok' => true,
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
