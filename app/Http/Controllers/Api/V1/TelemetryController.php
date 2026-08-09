<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTelemetryRequest;
use App\Models\Gateway;
use App\Services\TelemetryIngestionService;
use Illuminate\Http\JsonResponse;

class TelemetryController extends Controller
{
    public function __construct(private readonly TelemetryIngestionService $telemetry)
    {
    }

    public function store(StoreTelemetryRequest $request): JsonResponse
    {
        /** @var Gateway $gateway */
        $gateway = $request->attributes->get('authenticated_gateway');
        $this->telemetry->ingest($gateway, $request->validated());

        return response()->json(['status' => 'ok']);
    }
}
