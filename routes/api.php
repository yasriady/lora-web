<?php

use App\Http\Controllers\Api\V1\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('telemetry', [TelemetryController::class, 'store'])->middleware('gateway.token');
});
