<?php

use App\Http\Controllers\SpeedTestController;
use App\Http\Controllers\SpeedTestResultController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SpeedTestController::class, 'index'])->name('speedtest.show');

Route::post('/api/results', [SpeedTestResultController::class, 'store'])
    ->middleware('throttle:speedtest-results')
    ->name('speedtest.results.store');
