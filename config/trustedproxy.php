<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated proxy addresses, or "*" when every hop in front of
    | this app is a reverse proxy you control. Leave empty when clients
    | connect directly. Trusting "*" on a public origin lets clients spoof
    | X-Forwarded-For. Behind Cloudflare, list Cloudflare's ranges and the
    | test will prefer CF-Connecting-IP.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
