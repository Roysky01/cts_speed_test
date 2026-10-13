<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'CTS Speed Test') }}</title>
        <link rel="stylesheet" href="{{ asset('css/speedtest.css') }}?v={{ filemtime(public_path('css/speedtest.css')) }}">
    </head>
    <body>
        <header class="topbar">
            <div>
                <p class="eyebrow">Path test</p>
                <h1>CTS Speed Test</h1>
            </div>
            <p class="server-name">{{ $serverName }}</p>
        </header>

        <main>
            <section class="identity" aria-label="Client address">
                <div>
                    <p class="eyebrow">Your IP address</p>
                    <p class="ip" id="client-ip">{{ $ip }}</p>
                    @if ($remoteAddress !== '' && $remoteAddress !== $ip)
                        <p class="meta" id="remote-address">Socket address {{ $remoteAddress }}</p>
                    @else
                        <p class="meta" id="remote-address" hidden></p>
                    @endif
                </div>
                <div class="identity-side">
                    <p class="meta" id="transport">{{ $secure ? 'HTTPS' : 'HTTP' }} · measured in this browser</p>
                    <p class="meta truncate" id="user-agent">{{ $userAgent !== '' ? $userAgent : 'Unknown client' }}</p>
                </div>
            </section>

            <section class="stage" aria-labelledby="phase-label">
                <div class="gauge-wrap">
                    <svg class="gauge" viewBox="0 0 220 130" role="img" aria-labelledby="phase-label readout">
                        <path class="gauge-track" d="M 20 110 A 90 90 0 0 1 200 110"></path>
                        <path id="gauge-value" d="M 20 110 A 90 90 0 0 1 200 110"></path>
                    </svg>
                    <div class="readout">
                        <p id="phase-label">Ready</p>
                        <p id="readout">—</p>
                        <p id="readout-unit">Mbps</p>
                    </div>
                </div>
                <p class="scale" aria-hidden="true"><span>0</span><span>10</span><span>100</span><span>1000 Mbps</span></p>
                <svg id="sparkline" viewBox="0 0 320 56" aria-hidden="true">
                    <polyline points=""></polyline>
                </svg>
                <p id="transfer-summary" class="summary">Press start to measure the path from this browser to {{ $serverName }}.</p>
                <p id="error-banner" class="error" role="alert" hidden></p>
                <div class="controls">
                    <label>
                        Duration
                        <select id="duration">
                            <option value="5">5 seconds</option>
                            <option value="10" selected>10 seconds</option>
                            <option value="15">15 seconds</option>
                        </select>
                    </label>
                    <label>
                        Streams
                        <select id="streams">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="4" selected>4</option>
                            <option value="6">6</option>
                        </select>
                    </label>
                    <button type="button" id="start-test">Start test</button>
                </div>
                <p id="live-status" class="sr-only" aria-live="polite"></p>
            </section>

            <section class="readings" aria-label="Results">
                <article>
                    <p class="eyebrow">Ping</p>
                    <p><span id="ping-value">—</span> <span class="unit">ms</span></p>
                </article>
                <article>
                    <p class="eyebrow">Jitter</p>
                    <p><span id="jitter-value">—</span> <span class="unit">ms</span></p>
                </article>
                <article>
                    <p class="eyebrow">Download</p>
                    <p><span id="download-value">—</span> <span class="unit">Mbps</span></p>
                </article>
                <article>
                    <p class="eyebrow">Upload</p>
                    <p><span id="upload-value">—</span> <span class="unit">Mbps</span></p>
                </article>
                <article>
                    <p class="eyebrow">Loaded ping</p>
                    <p><span id="loaded-ping-value">—</span> <span class="unit">ms</span></p>
                </article>
            </section>

            <section class="history" aria-labelledby="history-heading">
                <h2 id="history-heading">Recent tests from this IP</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Ping</th>
                                <th>Download</th>
                                <th>Upload</th>
                                <th>Loaded ping</th>
                            </tr>
                        </thead>
                        <tbody id="history-body">
                            @forelse ($history as $test)
                                <tr>
                                    <td><time datetime="{{ $test->created_at?->toIso8601String() }}">{{ $test->created_at?->timezone(config('app.timezone'))->format('M j, H:i') }}</time></td>
                                    <td>{{ number_format((float) $test->ping_ms, 1) }} ms</td>
                                    <td>{{ number_format((float) $test->download_mbps, 2) }} Mbps</td>
                                    <td>{{ number_format((float) $test->upload_mbps, 2) }} Mbps</td>
                                    <td>{{ $test->loaded_ping_ms === null ? '—' : number_format((float) $test->loaded_ping_ms, 1).' ms' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">No tests from this IP yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="method">
                <h2>How the measurement works</h2>
                <ol>
                    <li>Latency sends eight tiny requests. The first warms the connection and is discarded. Ping is the median of the rest. Jitter is the average change from one sample to the next.</li>
                    <li>Download opens several streams at once and counts bytes as they arrive. The payload is random data, sent with caching and compression disabled, so the result is the path to this server.</li>
                    <li>Upload sends that same kind of data back. Parallel streams are used because one TCP connection often cannot fill the link by itself.</li>
                    <li>Mbps = (bytes × 8) ÷ (seconds × 1,000,000). The opening portion of each transfer is left out so TCP slow-start does not decide the result. A few pings during the download are reported as loaded ping.</li>
                </ol>
            </section>
        </main>

        <script id="speedtest-config" type="application/json">
            @json($config)
        </script>
        <script src="{{ asset('js/speedtest.js') }}?v={{ filemtime(public_path('js/speedtest.js')) }}" defer></script>
    </body>
</html>
