<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadPayloadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $maxBytes = (int) config('speedtest.max_upload_bytes');
        $declared = (int) $request->server('CONTENT_LENGTH', 0);

        if ($declared > $maxBytes) {
            return $this->tooLarge();
        }

        $bytes = strlen($request->getContent());

        if ($bytes > $maxBytes) {
            return $this->tooLarge();
        }

        return response()->json([
            'bytes' => $bytes,
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function tooLarge(): JsonResponse
    {
        return response()->json([
            'message' => 'Upload is larger than this server accepts.',
        ], 413);
    }
}
