<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportStatusController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PollutionReportController;
use App\Http\Middleware\AdminPrivacy;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/map')->name('home');
Route::get('/map', [MapController::class, 'index'])->name('map.index');
Route::get('/reports/create', [PollutionReportController::class, 'create'])->name('reports.create');
Route::post('/reports', [PollutionReportController::class, 'store'])
    ->middleware('throttle:pollution-reports')->name('reports.store');

Route::prefix('admin')->name('admin.')->middleware(AdminPrivacy::class)->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    Route::middleware(['auth', 'can:access-admin'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{pollutionReport}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{pollutionReport}/photo', [ReportController::class, 'photo'])->name('reports.photo');
        Route::patch('/reports/{pollutionReport}/status', ReportStatusController::class)
            ->middleware('throttle:admin-reviews')->name('reports.status');
    });
});
