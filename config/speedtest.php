<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server label
    |--------------------------------------------------------------------------
    |
    | Shown on the test page so the visitor knows which machine they are
    | measuring against. This is the server hosting the application.
    |
    */

    'server_name' => env('SPEEDTEST_SERVER_NAME', 'This server'),

    /*
    |--------------------------------------------------------------------------
    | Payload
    |--------------------------------------------------------------------------
    |
    | Download tests repeat this incompressible file. Keeping one random
    | file on disk avoids spending CPU on random_bytes() during the test,
    | which would make the result measure PHP instead of the network.
    |
    */

    'payload_bytes' => (int) env('SPEEDTEST_PAYLOAD_BYTES', 16 * 1024 * 1024),

    'max_download_bytes' => (int) env('SPEEDTEST_MAX_DOWNLOAD_BYTES', 256 * 1024 * 1024),

    'max_stream_seconds' => (int) env('SPEEDTEST_MAX_STREAM_SECONDS', 25),

    'max_upload_bytes' => (int) env('SPEEDTEST_MAX_UPLOAD_BYTES', 32 * 1024 * 1024),

    'upload_chunk_bytes' => (int) env('SPEEDTEST_UPLOAD_CHUNK_BYTES', 2 * 1024 * 1024),

];
