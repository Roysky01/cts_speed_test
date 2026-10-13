<?php

use App\Http\Controllers\ClientInfoController;
use App\Http\Controllers\DownloadPayloadController;
use App\Http\Controllers\LatencyController;
use App\Http\Controllers\SpeedTestResultController;
use App\Http\Controllers\UploadPayloadController;
use Illuminate\Support\Facades\Route;

/*
| Measurement routes stay off the web middleware group. The session driver
| locks the session for the whole request, which would force parallel
| download and upload streams to run one at a time.
*/

Route::get('/api/client', [ClientInfoController::class, 'show'])->name('speedtest.client');
Route::get('/api/latency', [LatencyController::class, 'show'])->name('speedtest.latency');
Route::get('/api/download', [DownloadPayloadController::class, 'show'])->name('speedtest.download');
Route::post('/api/upload', [UploadPayloadController::class, 'store'])->name('speedtest.upload');
Route::get('/api/results', [SpeedTestResultController::class, 'index'])->name('speedtest.results.index');
