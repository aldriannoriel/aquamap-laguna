<?php

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IncidentMunicipalityController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportEvidenceController;
use App\Http\Controllers\Admin\ReportStatusController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PollutionReportController;
use App\Http\Controllers\ReportOtpController;
use App\Http\Middleware\AdminPrivacy;
use Illuminate\Support\Facades\Route;

// Public AquaMap pages.
Route::view('/', 'landing')->name('home');

Route::get('/map', [MapController::class, 'index'])
    ->name('map.index');

// Citizen pollution reporting.
Route::get('/reports/create', [PollutionReportController::class, 'create'])
    ->name('reports.create');

// Email OTP verification.
Route::post('/reports/otp/send', [ReportOtpController::class, 'send'])
    ->name('reports.otp.send');

Route::post('/reports/otp/verify', [ReportOtpController::class, 'verify'])
    ->name('reports.otp.verify');

// Submit a verified pollution report.
Route::post('/reports', [PollutionReportController::class, 'store'])
    ->middleware('throttle:pollution-reports')
    ->name('reports.store');

// Existing administration routes.
Route::prefix('admin')
    ->name('admin.')
    ->middleware(AdminPrivacy::class)
    ->group(function (): void {
        Route::get('/login', [SessionController::class, 'create'])
            ->name('login');

        Route::post('/login', [SessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');

        Route::middleware(['auth', 'can:access-admin'])
            ->group(function (): void {
                Route::resource('agencies', AgencyController::class)
                    ->except('show');

                Route::post(
                    '/reports/{pollutionReport}/incident-municipality',
                    IncidentMunicipalityController::class
                )
                    ->middleware('throttle:admin-reviews')
                    ->name('reports.municipality');

                Route::get(
                    '/reports/{pollutionReport}/delivery-status',
                    [ReferralController::class, 'status']
                )->name('referrals.status');

                Route::post(
                    '/reports/{pollutionReport}/delivery/{referral}/reconcile',
                    [ReferralController::class, 'reconcile']
                )
                    ->middleware('throttle:admin-reviews')
                    ->name('referrals.reconcile');

                Route::post(
                    '/reports/{pollutionReport}/delivery/{referral}/retry',
                    [ReferralController::class, 'retry']
                )->name('referrals.retry');

                Route::post(
                    '/reports/{pollutionReport}/delivery/{referral}/confirm',
                    [ReferralController::class, 'confirm']
                )->name('referrals.confirm');

                Route::get(
                    '/reports/{pollutionReport}/referral',
                    [ReferralController::class, 'preview']
                )->name('referrals.preview');

                Route::post(
                    '/reports/{pollutionReport}/referral/preview',
                    [ReferralController::class, 'review']
                )
                    ->middleware('throttle:admin-reviews')
                    ->name('referrals.review');

                Route::post(
                    '/reports/{pollutionReport}/referral',
                    [ReferralController::class, 'store']
                )->name('referrals.store');

                Route::get('/', DashboardController::class)
                    ->name('dashboard');

                Route::post('/logout', [SessionController::class, 'destroy'])
                    ->name('logout');

                Route::get('/reports', [ReportController::class, 'index'])
                    ->name('reports.index');

                Route::get(
                    '/reports/{pollutionReport}',
                    [ReportController::class, 'show']
                )->name('reports.show');

                Route::get(
                    '/reports/{pollutionReport}/photo',
                    [ReportController::class, 'photo']
                )->name('reports.photo');

                Route::get(
                    '/reports/{pollutionReport}/evidence/{evidence}',
                    [ReportEvidenceController::class, 'show']
                )->name('reports.evidence');

                Route::patch(
                    '/reports/{pollutionReport}/status',
                    ReportStatusController::class
                )
                    ->middleware('throttle:admin-reviews')
                    ->name('reports.status');
            });
    });
