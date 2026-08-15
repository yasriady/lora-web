<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GatewayController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\NodeController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', ['en', 'id'])
    ->name('locale.update');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/widgets/kpis', [DashboardController::class, 'widgetKpis'])->name('dashboard.widgets.kpis');
    Route::get('/dashboard/widgets/telemetry', [DashboardController::class, 'widgetTelemetry'])->name('dashboard.widgets.telemetry');
    Route::get('/dashboard/widgets/logs', [DashboardController::class, 'widgetLogs'])->name('dashboard.widgets.logs');
    Route::get('/dashboard/widgets/chart', [DashboardController::class, 'widgetChart'])->name('dashboard.widgets.chart');

    Route::get('/gateways', [GatewayController::class, 'index'])->name('gateways.index');
    Route::post('/gateways', [GatewayController::class, 'store'])->name('gateways.store');
    Route::put('/gateways/{gateway}', [GatewayController::class, 'update'])->name('gateways.update');
    Route::patch('/gateways/{gateway}/toggle', [GatewayController::class, 'toggle'])->name('gateways.toggle');
    Route::delete('/gateways/{gateway}', [GatewayController::class, 'destroy'])->name('gateways.destroy');

    Route::get('/nodes', [NodeController::class, 'index'])->name('nodes.index');
    Route::post('/nodes', [NodeController::class, 'store'])->name('nodes.store');
    Route::put('/nodes/{node}', [NodeController::class, 'update'])->name('nodes.update');
    Route::patch('/nodes/{node}/toggle', [NodeController::class, 'toggle'])->name('nodes.toggle');
    Route::delete('/nodes/{node}', [NodeController::class, 'destroy'])->name('nodes.destroy');

    Route::get('/telemetry', [DashboardController::class, 'telemetry'])->name('telemetry.index');
    Route::get('/telemetry/export', [DashboardController::class, 'exportTelemetry'])->name('telemetry.export');
    Route::get('/logs', [DashboardController::class, 'logs'])->name('logs.index');
    Route::view('/map', 'placeholder')->name('map');
    Route::view('/settings', 'placeholder')->name('settings');
});
