<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\GatewayLog;
use App\Models\Node;
use App\Models\Telemetry;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TelemetryIngestionService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function ingest(Gateway $gateway, array $payload): void
    {
        if ($gateway->gateway_id !== $payload['gateway_id']) {
            throw new HttpException(401, 'Gateway identity does not match the bearer token.');
        }

        $node = Node::query()->where('gateway_id', $gateway->gateway_id)
            ->where('node_id', $payload['node_id'])->where('enabled', true)->first();

        if ($node === null) {
            throw new HttpException(404, 'Node is not registered or is disabled for this gateway.');
        }

        DB::transaction(function () use ($gateway, $node, $payload): void {
            Telemetry::query()->create($payload);
            $gateway->forceFill(['last_seen' => now()])->save();
            $node->forceFill(['last_seen' => now()])->save();
            GatewayLog::query()->create([
                'gateway_id' => $gateway->gateway_id,
                'level' => 'info',
                'event' => 'telemetry_received',
                'message' => "Telemetry received from node {$node->node_id}.",
            ]);
        });
    }
}
