<?php

use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/gateways', [DashboardController::class, 'gateways'])->name('gateways.index');
Route::post('/gateways', [DashboardController::class, 'storeGateway'])->name('gateways.store');
Route::get('/nodes', [DashboardController::class, 'nodes'])->name('nodes.index');
Route::post('/nodes', [DashboardController::class, 'storeNode'])->name('nodes.store');
Route::get('/telemetry', [DashboardController::class, 'telemetry'])->name('telemetry.index');
Route::get('/logs', [DashboardController::class, 'logs'])->name('logs.index');
Route::view('/map', 'placeholder')->name('map');
Route::view('/settings', 'placeholder')->name('settings');
