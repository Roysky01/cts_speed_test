(function () {
    'use strict';

    var configNode = document.getElementById('speedtest-config');

    if (!configNode) {
        return;
    }

    var config = JSON.parse(configNode.textContent);
    var GAUGE_LENGTH = 282.743;
    var running = false;
    var stopRequested = false;

    var phaseLabel = document.getElementById('phase-label');
    var readout = document.getElementById('readout');
    var readoutUnit = document.getElementById('readout-unit');
    var gauge = document.getElementById('gauge-value');
    var summary = document.getElementById('transfer-summary');
    var errorBanner = document.getElementById('error-banner');
    var liveStatus = document.getElementById('live-status');
    var startButton = document.getElementById('start-test');
    var durationSelect = document.getElementById('duration');
    var streamsSelect = document.getElementById('streams');
    var sparkline = document.querySelector('#sparkline polyline');

    startButton.addEventListener('click', function () {
        if (running) {
            stopRequested = true;
            return;
        }

        runTest();
    });

    refreshClient();

    function runTest() {
        running = true;
        stopRequested = false;
        clearError();
        setBusy(true);
        resetReadings();
        setPhase('Latency', '—', 'ms', 0);

        var durationMs = selectedDuration();
        var streams = selectedStreams();
        var loadedSamples = [];

        measureLatency(config.latencyUrl, 8)
            .then(function (latency) {
                if (stopRequested) {
                    throw new Error('Stopped');
                }

                document.getElementById('ping-value').textContent = formatMs(latency.ping);
                document.getElementById('jitter-value').textContent = formatMs(latency.jitter);
                setPhase('Download', '0.00', 'Mbps', 0);

                return measureTransfer({
                    mode: 'download',
                    durationMs: durationMs,
                    streams: streams,
                    onSample: function (mbps, samples) {
                        setPhase('Download', formatMbps(mbps), 'Mbps', gaugeRatio(mbps));
                        renderSparkline(samples);
                    },
                    onLoadedPing: function (value) {
                        loadedSamples.push(value);
                        document.getElementById('loaded-ping-value').textContent = formatMs(median(loadedSamples));
                    },
                });
            })
            .then(function (download) {
                if (stopRequested) {
                    throw new Error('Stopped');
                }

                document.getElementById('download-value').textContent = formatMbps(download.mbps);
                setPhase('Upload', '0.00', 'Mbps', 0);

                return measureTransfer({
                    mode: 'upload',
                    durationMs: durationMs,
                    streams: streams,
                    onSample: function (mbps, samples) {
                        setPhase('Upload', formatMbps(mbps), 'Mbps', gaugeRatio(mbps));
                        renderSparkline(samples);
                        gauge.style.stroke = '#e2a23b';
                    },
                }).then(function (upload) {
                    return { download: download, upload: upload };
                });
            })
            .then(function (result) {
                document.getElementById('upload-value').textContent = formatMbps(result.upload.mbps);
                summary.textContent = formatMegabytes(result.download.bytes) + ' MB down in ' + (result.download.milliseconds / 1000).toFixed(1) + ' s · ' + formatMegabytes(result.upload.bytes) + ' MB up in ' + (result.upload.milliseconds / 1000).toFixed(1) + ' s';
                setPhase('Saving', formatMbps(result.download.mbps), 'Mbps', gaugeRatio(result.download.mbps));

                return saveResult(result, loadedSamples).then(function (saved) {
                    applyServerResult(saved.data);
                    renderHistory(saved.history || []);
                    setPhase('Done', formatMbps(saved.data.download_mbps), 'Mbps', gaugeRatio(saved.data.download_mbps));
                    announce('Test complete. Download ' + formatMbps(saved.data.download_mbps) + ' megabits per second.');
                });
            })
            .catch(function (error) {
                if (error && error.message === 'Stopped') {
                    summary.textContent = 'Test stopped.';
                    setPhase('Stopped', readout.textContent, readoutUnit.textContent, 0);
                    announce('Test stopped.');
                    return;
                }

                showError(error && error.message ? error.message : 'The test failed.');
                setPhase('Ready', '—', 'Mbps', 0);
            })
            .then(function () {
                running = false;
                gauge.style.stroke = '';
                setBusy(false);
            });
    }

    function measureLatency(url, samples) {
        var readings = [];
        var index = 0;

        function next() {
            if (stopRequested) {
                return Promise.reject(new Error('Stopped'));
            }

            if (index >= samples) {
                var usable = readings.slice(1);
                var source = usable.length > 0 ? usable : readings;

                return Promise.resolve({
                    ping: median(source),
                    jitter: jitter(source),
                });
            }

            index += 1;
            setPhase('Latency', readings.length ? formatMs(readings[readings.length - 1]) : '—', 'ms', index / samples);

            return sampleLatency(url).then(function (milliseconds) {
                readings.push(milliseconds);
                document.getElementById('ping-value').textContent = formatMs(milliseconds);
                return next();
            });
        }

        return next();
    }

    function sampleLatency(url) {
        var started = performance.now();

        return fetch(url + '?r=' + crypto.randomUUID(), {
            cache: 'no-store',
            headers: { 'Accept': 'application/json', 'Cache-Control': 'no-store' },
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Latency check failed.');
            }

            return response.json().then(function () {
                return performance.now() - started;
            });
        });
    }

    function measureTransfer(options) {
        var started = performance.now();
        var deadline = started + options.durationMs;
        var samples = [{ at: 0, bytes: 0 }];
        var finishedBytes = 0;
        var activeBytes = {};
        var nextId = 0;
        var payload = null;

        if (options.mode === 'upload') {
            var chunkSize = Math.min(config.uploadChunkBytes, config.maxUploadBytes);
            payload = createPayload(chunkSize);
        }

        function totalBytes() {
            var inflight = 0;
            Object.keys(activeBytes).forEach(function (key) {
                inflight += activeBytes[key];
            });

            return finishedBytes + inflight;
        }

        function noteProgress() {
            var at = performance.now() - started;
            samples.push({ at: at, bytes: totalBytes() });
            var recent = instantMbps(samples);
            options.onSample(recent, samples);
        }

        function oneDownload() {
            if (stopRequested || performance.now() >= deadline) {
                return Promise.resolve();
            }

            var controller = new AbortController();
            var timer = window.setInterval(function () {
                if (performance.now() >= deadline || stopRequested) {
                    controller.abort();
                }
            }, 200);

            return fetch(config.downloadUrl + '?bytes=' + config.maxDownloadBytes + '&r=' + crypto.randomUUID(), {
                cache: 'no-store',
                signal: controller.signal,
                headers: { 'Accept': 'application/octet-stream', 'Cache-Control': 'no-store' },
            }).then(function (response) {
                if (!response.ok || !response.body) {
                    throw new Error('Download failed.');
                }

                var reader = response.body.getReader();

                function read() {
                    return reader.read().then(function (chunk) {
                        if (chunk.done || stopRequested || performance.now() >= deadline) {
                            return reader.cancel().catch(function () {});
                        }

                        finishedBytes += chunk.value.byteLength;
                        noteProgress();

                        return read();
                    });
                }

                return read();
            }).catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                throw error;
            }).then(function () {
                window.clearInterval(timer);

                if (!stopRequested && performance.now() < deadline) {
                    return oneDownload();
                }
            });
        }

        function oneUpload() {
            if (stopRequested || performance.now() >= deadline) {
                return Promise.resolve();
            }

            var id = String(nextId++);
            activeBytes[id] = 0;

            return uploadOnce(payload, function (loaded) {
                activeBytes[id] = loaded;
                noteProgress();
            }, function () {
                return stopRequested || performance.now() >= deadline;
            }).then(function (received) {
                var counted = received && received.bytes ? received.bytes : activeBytes[id] || payload.byteLength;
                finishedBytes += counted;
                delete activeBytes[id];
                noteProgress();

                if (!stopRequested && performance.now() < deadline) {
                    return oneUpload();
                }
            }).catch(function (error) {
                var sent = activeBytes[id] || 0;
                delete activeBytes[id];

                if (error && error.message === 'aborted') {
                    finishedBytes += sent;
                    return;
                }

                throw error;
            });
        }

        var workers = [];
        var count = options.streams;
        var index;

        for (index = 0; index < count; index += 1) {
            workers.push(options.mode === 'download' ? oneDownload() : oneUpload());
        }

        var loadedTimer = null;

        if (options.onLoadedPing) {
            loadedTimer = window.setInterval(function () {
                sampleLatency(config.latencyUrl).then(function (milliseconds) {
                    options.onLoadedPing(milliseconds);
                }).catch(function () {});
            }, 1000);
        }

        return Promise.all(workers).then(function () {
            if (loadedTimer) {
                window.clearInterval(loadedTimer);
            }

            noteProgress();

            return summarize(samples, options.durationMs);
        }).catch(function (error) {
            if (loadedTimer) {
                window.clearInterval(loadedTimer);
            }

            throw error;
        });
    }

    function uploadOnce(payload, onProgress, shouldStop) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            var settled = false;

            function finish(error, value) {
                if (settled) {
                    return;
                }

                settled = true;
                window.clearInterval(watch);

                if (error) {
                    reject(error);
                    return;
                }

                resolve(value);
            }

            var watch = window.setInterval(function () {
                if (shouldStop()) {
                    xhr.abort();
                }
            }, 200);

            xhr.open('POST', config.uploadUrl);
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = function (event) {
                if (event.lengthComputable) {
                    onProgress(event.loaded);
                }
            };
            xhr.onload = function () {
                if (xhr.status === 413) {
                    finish(new Error('This server rejected the upload. Raise post_max_size above the upload chunk.'));
                    return;
                }

                if (xhr.status < 200 || xhr.status >= 300) {
                    finish(new Error('Upload failed.'));
                    return;
                }

                try {
                    finish(null, JSON.parse(xhr.responseText));
                } catch (error) {
                    finish(new Error('Upload failed.'));
                }
            };
            xhr.onerror = function () {
                finish(new Error('Upload failed.'));
            };
            xhr.onabort = function () {
                finish(new Error('aborted'));
            };
            xhr.send(payload);
        });
    }

    function saveResult(result, loadedSamples) {
        if (result.download.milliseconds < 200 || result.upload.milliseconds < 200) {
            return Promise.reject(new Error('The transfer window was too short to save.'));
        }

        var body = {
            ping_ms: round(medianPingFromDom('ping-value'), 2),
            jitter_ms: round(medianPingFromDom('jitter-value'), 2),
            loaded_ping_ms: loadedSamples.length ? round(median(loadedSamples), 2) : null,
            download_bytes: Math.round(result.download.bytes),
            download_ms: Math.round(result.download.milliseconds),
            upload_bytes: Math.round(result.upload.bytes),
            upload_ms: Math.round(result.upload.milliseconds),
        };

        return fetch(config.resultsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(body),
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('The result could not be saved.');
            }

            return response.json();
        });
    }

    function refreshClient() {
        fetch(config.clientUrl, {
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
        }).then(function (response) {
            if (!response.ok) {
                return null;
            }

            return response.json();
        }).then(function (client) {
            if (!client) {
                return;
            }

            document.getElementById('client-ip').textContent = client.ip;
            document.getElementById('transport').textContent = (client.secure ? 'HTTPS' : 'HTTP') + ' · measured in this browser';
            document.getElementById('user-agent').textContent = client.user_agent || 'Unknown client';

            var remote = document.getElementById('remote-address');

            if (client.proxied && client.remote_addr) {
                remote.hidden = false;
                remote.textContent = 'Socket address ' + client.remote_addr;
            }
        }).catch(function () {});
    }

    function applyServerResult(result) {
        document.getElementById('ping-value').textContent = formatMs(result.ping_ms);
        document.getElementById('jitter-value').textContent = formatMs(result.jitter_ms);
        document.getElementById('download-value').textContent = formatMbps(result.download_mbps);
        document.getElementById('upload-value').textContent = formatMbps(result.upload_mbps);
        document.getElementById('loaded-ping-value').textContent = result.loaded_ping_ms === null ? '—' : formatMs(result.loaded_ping_ms);
    }

    function renderHistory(tests) {
        var body = document.getElementById('history-body');
        body.replaceChildren();

        if (!tests.length) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 5;
            emptyCell.textContent = 'No tests from this IP yet.';
            emptyRow.append(emptyCell);
            body.append(emptyRow);
            return;
        }

        tests.forEach(function (test) {
            var row = document.createElement('tr');
            [formatWhen(test.tested_at), formatMs(test.ping_ms) + ' ms', formatMbps(test.download_mbps) + ' Mbps', formatMbps(test.upload_mbps) + ' Mbps', test.loaded_ping_ms === null ? '—' : formatMs(test.loaded_ping_ms) + ' ms'].forEach(function (value) {
                var cell = document.createElement('td');
                cell.textContent = value;
                row.append(cell);
            });
            body.append(row);
        });
    }

    function summarize(samples, durationMs) {
        var end = samples[samples.length - 1];
        var warmupMs = Math.min(1500, durationMs * 0.2);
        var start = samples[0];
        var index;

        for (index = 0; index < samples.length; index += 1) {
            if (samples[index].at <= warmupMs) {
                start = samples[index];
            }
        }

        var bytes = end.bytes - start.bytes;
        var milliseconds = end.at - start.at;

        if (milliseconds < 200 || bytes <= 0) {
            bytes = end.bytes - samples[0].bytes;
            milliseconds = end.at - samples[0].at;
        }

        return {
            bytes: Math.max(0, bytes),
            milliseconds: Math.max(0, milliseconds),
            mbps: megabitsPerSecond(bytes, milliseconds),
        };
    }

    function instantMbps(samples) {
        var latest = samples[samples.length - 1];
        var earlier = samples[0];
        var index;

        for (index = samples.length - 1; index >= 0; index -= 1) {
            if (latest.at - samples[index].at >= 400) {
                earlier = samples[index];
                break;
            }
        }

        return megabitsPerSecond(latest.bytes - earlier.bytes, latest.at - earlier.at);
    }

    function renderSparkline(samples) {
        if (samples.length < 2) {
            sparkline.setAttribute('points', '');
            return;
        }

        var width = 320;
        var height = 56;
        var lastAt = samples[samples.length - 1].at || 1;
        var points = [];
        var maxMbps = 1;
        var index;

        for (index = 1; index < samples.length; index += 1) {
            var mbps = megabitsPerSecond(samples[index].bytes - samples[index - 1].bytes, samples[index].at - samples[index - 1].at);
            if (!Number.isFinite(mbps) || mbps < 0) {
                mbps = 0;
            }
            points.push({ at: samples[index].at, mbps: mbps });
            if (mbps > maxMbps) {
                maxMbps = mbps;
            }
        }

        sparkline.setAttribute('points', points.map(function (point) {
            var x = (point.at / lastAt) * width;
            var y = height - (point.mbps / maxMbps) * (height - 4) - 2;
            return x.toFixed(1) + ',' + y.toFixed(1);
        }).join(' '));
    }

    function megabitsPerSecond(bytes, milliseconds) {
        if (milliseconds <= 0 || bytes <= 0) {
            return 0;
        }

        return (bytes * 8) / (milliseconds / 1000) / 1000000;
    }

    function median(values) {
        if (!values.length) {
            return 0;
        }

        var sorted = values.slice().sort(function (a, b) { return a - b; });
        var mid = Math.floor(sorted.length / 2);

        if (sorted.length % 2 === 0) {
            return (sorted[mid - 1] + sorted[mid]) / 2;
        }

        return sorted[mid];
    }

    function jitter(values) {
        if (values.length < 2) {
            return 0;
        }

        var total = 0;
        var index;

        for (index = 1; index < values.length; index += 1) {
            total += Math.abs(values[index] - values[index - 1]);
        }

        return total / (values.length - 1);
    }

    function gaugeRatio(mbps) {
        var ratio = Math.log10(Math.max(0, mbps) + 1) / Math.log10(1001);
        return Math.max(0, Math.min(1, ratio));
    }

    function setPhase(phase, value, unit, ratio) {
        phaseLabel.textContent = phase;
        readout.textContent = value;
        readoutUnit.textContent = unit;
        gauge.style.strokeDashoffset = String(GAUGE_LENGTH * (1 - ratio));
        announce(phase + ' ' + value + ' ' + unit);
    }

    function announce(text) {
        liveStatus.textContent = text;
    }

    function setBusy(isBusy) {
        durationSelect.disabled = isBusy;
        streamsSelect.disabled = isBusy;
        startButton.textContent = isBusy ? 'Stop' : 'Start test';
        startButton.classList.toggle('stop', isBusy);
    }

    function resetReadings() {
        ['ping-value', 'jitter-value', 'download-value', 'upload-value', 'loaded-ping-value'].forEach(function (id) {
            document.getElementById(id).textContent = '—';
        });
        sparkline.setAttribute('points', '');
        summary.textContent = 'Measuring the path from this browser to ' + config.serverName + '.';
    }

    function showError(message) {
        errorBanner.hidden = false;
        errorBanner.textContent = message;
        announce(message);
    }

    function clearError() {
        errorBanner.hidden = true;
        errorBanner.textContent = '';
    }

    function selectedDuration() {
        var value = Number(durationSelect.value);
        if (value === 5 || value === 15) {
            return value * 1000;
        }

        return 10000;
    }

    function selectedStreams() {
        var value = Number(streamsSelect.value);
        if (value === 1 || value === 2 || value === 6) {
            return value;
        }

        return 4;
    }

    function formatMbps(value) {
        return Number(value).toFixed(2);
    }

    function formatMs(value) {
        return Number(value).toFixed(1);
    }

    function formatMegabytes(bytes) {
        return (bytes / 1000000).toFixed(1);
    }

    function formatWhen(iso) {
        var date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        return date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function round(value, places) {
        var factor = Math.pow(10, places);
        return Math.round(value * factor) / factor;
    }

    function medianPingFromDom(id) {
        return Number(document.getElementById(id).textContent);
    }

    function createPayload(size) {
        var buffer = new Uint8Array(size);
        var offset = 0;

        while (offset < size) {
            var end = Math.min(offset + 65536, size);
            crypto.getRandomValues(buffer.subarray(offset, end));
            offset = end;
        }

        return buffer;
    }
})();
