<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\GatewayLog;
use App\Models\Node;
use App\Models\Telemetry;
use App\Models\TelemetryReading;
use App\Support\NodeMetricCatalog;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TelemetryIngestionService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingest(Gateway $gateway, array $payload): void
    {
        if ($gateway->gateway_id !== $payload['gateway_id']) {
            throw new HttpException(401, 'Gateway identity does not match the bearer token.');
        }

        $node = Node::query()
            ->where('gateway_id', $gateway->gateway_id)
            ->where('node_id', $payload['node_id'])
            ->where('enabled', true)
            ->first();

        if ($node === null) {
            throw new HttpException(404, 'Node is not registered or is disabled for this gateway.');
        }

        $metrics = NodeMetricCatalog::normalizeNumericMetrics($payload['metrics'] ?? []);
        $seq = $this->normalizeSeq($payload['seq'] ?? $metrics['seq'] ?? null);
        unset($metrics['seq']);

        if ($metrics === []) {
            throw new HttpException(400, 'Telemetry metrics must contain at least one numeric value.');
        }

        $schema = $node->resolvedMetricsSchema();

        DB::transaction(function () use ($gateway, $node, $payload, $metrics, $schema, $seq): void {
            $telemetry = Telemetry::query()->create([
                'gateway_id' => $payload['gateway_id'],
                'node_id' => $payload['node_id'],
                'timestamp' => $payload['timestamp'],
                'metrics' => $metrics,
                'temperature' => $metrics['temperature'] ?? null,
                'humidity' => $metrics['humidity'] ?? null,
                'battery' => $payload['battery'] ?? null,
                'rssi' => $payload['rssi'] ?? null,
                'snr' => $payload['snr'] ?? null,
                'seq' => $seq,
            ]);

            $readingRows = [];
            foreach ($metrics as $key => $value) {
                $readingRows[] = [
                    'telemetry_id' => $telemetry->id,
                    'gateway_id' => $gateway->gateway_id,
                    'node_id' => $node->node_id,
                    'timestamp' => $telemetry->timestamp,
                    'metric_key' => $key,
                    'metric_value' => $value,
                    'unit' => NodeMetricCatalog::unitFor($key, $schema),
                    'created_at' => now(),
                ];
            }

            TelemetryReading::query()->insert($readingRows);

            $gateway->forceFill(['last_seen' => now()])->save();
            $node->forceFill(['last_seen' => now()])->save();

            GatewayLog::query()->create([
                'gateway_id' => $gateway->gateway_id,
                'level' => 'info',
                'event' => 'telemetry_received',
                'message' => 'Telemetry received from node '.$node->node_id
                    .($seq === null ? '' : ' seq='.$seq)
                    .' ('.implode(', ', array_keys($metrics)).').',
            ]);
        });
    }

    private function normalizeSeq(mixed $value): ?int
    {
        if ($value === null || $value === '' || is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        $seq = (int) $value;

        if ($seq < 1 || $seq > 4294967295) {
            return null;
        }

        return $seq;
    }
}
