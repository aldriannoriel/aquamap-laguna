<?php

use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/map')->name('home');
Route::get('/map', [MapController::class, 'index'])->name('map.index');
