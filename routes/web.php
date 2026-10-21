<?php

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReferralController;
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
        Route::resource('agencies', AgencyController::class)->except('show');
        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
        Route::post('/referrals/test', [ReferralController::class, 'test'])->middleware('throttle:5,60')->name('referrals.test');
        Route::post('/referrals/{referral}/retry', [ReferralController::class, 'retry'])->name('referrals.retry');
        Route::post('/referrals/{referral}/confirm', [ReferralController::class, 'confirm'])->name('referrals.confirm');
        Route::get('/reports/{pollutionReport}/referral', [ReferralController::class, 'preview'])->name('referrals.preview');
        Route::post('/reports/{pollutionReport}/referral', [ReferralController::class, 'store'])->name('referrals.store');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{pollutionReport}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('/reports/{pollutionReport}/photo', [ReportController::class, 'photo'])->name('reports.photo');
        Route::patch('/reports/{pollutionReport}/status', ReportStatusController::class)
            ->middleware('throttle:admin-reviews')->name('reports.status');
    });
});
