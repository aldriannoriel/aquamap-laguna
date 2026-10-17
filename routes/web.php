<?php

use App\Http\Controllers\MapController;
use App\Http\Controllers\PollutionReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/map')->name('home');
Route::get('/map', [MapController::class, 'index'])->name('map.index');
Route::get('/reports/create', [PollutionReportController::class, 'create'])->name('reports.create');
Route::post('/reports', [PollutionReportController::class, 'store'])
    ->middleware('throttle:pollution-reports')->name('reports.store');
