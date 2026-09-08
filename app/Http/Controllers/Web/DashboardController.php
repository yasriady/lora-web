<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\GatewayLog;
use App\Models\Node;
use App\Models\Telemetry;
use App\Models\TelemetryReading;
use App\Support\PacketDeliveryRatio;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(): View
    {
        $kpis = $this->kpiPayload();

        return view('dashboard.index', [
            ...$kpis,
            ...$this->graphFlags(),
            'recentTelemetry' => Telemetry::query()->latest('timestamp')->limit(8)->get(),
            'recentLogs' => GatewayLog::query()->latest('created_at')->limit(8)->get(),
            'chartGateways' => Gateway::query()->orderBy('gateway_id')->get(['gateway_id', 'name']),
            'chartNodes' => Node::query()->orderBy('gateway_id')->orderBy('node_id')->get(['gateway_id', 'node_id', 'name']),
        ]);
    }

    public function widgetKpis(): JsonResponse
    {
        return response()->json($this->kpiPayload());
    }

    public function widgetTelemetry(): JsonResponse
    {
        $items = Telemetry::query()->latest('timestamp')->limit(8)->get();

        return response()->json([
            'empty' => $items->isEmpty(),
            'emptyText' => __('ui.dashboard.no_telemetry'),
            'rows' => $items->map(static function (Telemetry $item): array {
                $metrics = [];
                foreach ($item->displayMetrics() as $key => $value) {
                    $metrics[] = [
                        'key' => (string) $key,
                        'value' => is_scalar($value) ? (string) $value : json_encode($value),
                    ];
                }

                return [
                    'time' => $item->timestamp?->diffForHumans() ?? '—',
                    'node_id' => (string) $item->node_id,
                    'gateway_id' => (string) $item->gateway_id,
                    'metrics' => $metrics,
                    'seq' => $item->seq === null ? '—' : (string) $item->seq,
                    'rssi' => $item->rssi === null ? '—' : (string) $item->rssi,
                ];
            })->values(),
        ]);
    }

    public function widgetLogs(): JsonResponse
    {
        $items = GatewayLog::query()->latest('created_at')->limit(8)->get();

        return response()->json([
            'empty' => $items->isEmpty(),
            'emptyText' => __('ui.dashboard.no_logs'),
            'rows' => $items->map(static function (GatewayLog $log): array {
                $level = (string) $log->level;

                return [
                    'event' => (string) $log->event,
                    'message' => Str::limit((string) $log->message, 70),
                    'time' => $log->created_at?->diffForHumans() ?? '—',
                    'level' => $level,
                    'levelClass' => match ($level) {
                        'warning' => 'badge-offline',
                        'error' => 'badge-danger',
                        default => 'badge-info',
                    },
                ];
            })->values(),
        ]);
    }

    public function widgetChart(Request $request): JsonResponse
    {
        $flags = $this->graphFlags();
        if (! $flags['showEnvGraph']) {
            return response()->json([
                'enabled' => false,
                'series' => [],
                'options' => $this->chartFilterOptions(),
            ]);
        }

        $metrics = $this->enabledGraphMetrics();
        $windowMinutes = max(5, (int) config('lora.graph_window_minutes', 30));
        $maxNodes = max(1, (int) config('lora.graph_max_series', 8));
        $from = now()->subMinutes($windowMinutes);

        $gatewayId = $request->string('gateway_id')->toString();
        $nodeId = $request->string('node_id')->toString();
        $since = $this->parseSince($request->string('since')->toString(), $from);

        $pairs = $this->resolveChartNodePairs($gatewayId, $nodeId, $metrics, $from, $maxNodes);
        $truncated = (bool) ($pairs['truncated'] ?? false);
        /** @var Collection<int, object> $selected */
        $selected = $pairs['nodes'];

        $series = [];
        $latest = null;

        if ($selected->isNotEmpty()) {
            $readings = TelemetryReading::query()
                ->whereIn('metric_key', $metrics)
                ->where('timestamp', '>=', $from)
                ->when($since, fn ($query) => $query->where('timestamp', '>', $since))
                ->where(function ($query) use ($selected): void {
                    foreach ($selected as $index => $pair) {
                        $query->{$index === 0 ? 'where' : 'orWhere'}(function ($inner) use ($pair): void {
                            $inner->where('gateway_id', $pair->gateway_id)
                                ->where('node_id', $pair->node_id);
                        });
                    }
                })
                ->orderBy('timestamp')
                ->limit(4000)
                ->get();

            $grouped = $readings->groupBy(fn (TelemetryReading $row): string => $row->gateway_id.'|'.$row->node_id.'|'.$row->metric_key);

            foreach ($selected as $pair) {
                foreach ($metrics as $metric) {
                    $key = $pair->gateway_id.'|'.$pair->node_id.'|'.$metric;
                    $points = ($grouped[$key] ?? collect())->map(function (TelemetryReading $row) use (&$latest): array {
                        $time = $row->timestamp?->toIso8601String();
                        if ($time && ($latest === null || $time > $latest)) {
                            $latest = $time;
                        }

                        return [
                            't' => $time,
                            'v' => $row->metric_value === null ? null : (float) $row->metric_value,
                        ];
                    })->values();

                    $lastPoint = $points->last();
                    if ($since === null && $points->isEmpty()) {
                        continue;
                    }

                    $series[] = [
                        'id' => $key,
                        'gateway_id' => $pair->gateway_id,
                        'node_id' => $pair->node_id,
                        'label' => $this->chartSeriesLabel($pair, $metric),
                        'metric' => $metric,
                        'unit' => $metric === 'humidity' ? '%' : '°C',
                        'axis' => $metric === 'humidity' ? 'humidity' : 'temperature',
                        'last' => is_array($lastPoint) ? $lastPoint['v'] : null,
                        'points' => $points,
                    ];
                }
            }
        }

        return response()->json([
            'enabled' => true,
            'truncated' => $truncated,
            'windowMinutes' => $windowMinutes,
            'metrics' => $metrics,
            'latest' => $latest,
            'series' => $series,
            'options' => $this->chartFilterOptions(),
        ]);
    }

    /**
     * @return array{showTemperatureGraph: bool, showHumidityGraph: bool, showEnvGraph: bool}
     */
    private function graphFlags(): array
    {
        $showTemperatureGraph = (bool) config('lora.show_temperature_graph');
        $showHumidityGraph = (bool) config('lora.show_humidity_graph');

        return [
            'showTemperatureGraph' => $showTemperatureGraph,
            'showHumidityGraph' => $showHumidityGraph,
            'showEnvGraph' => $showTemperatureGraph || $showHumidityGraph,
        ];
    }

    /**
     * @return list<string>
     */
    private function enabledGraphMetrics(): array
    {
        $metrics = [];
        if (config('lora.show_temperature_graph')) {
            $metrics[] = 'temperature';
        }
        if (config('lora.show_humidity_graph')) {
            $metrics[] = 'humidity';
        }

        return $metrics;
    }

    /**
     * @return array{gateways: list<array{id: string, name: string}>, nodes: list<array{id: string, gateway_id: string, name: string}>}
     */
    private function chartFilterOptions(): array
    {
        return [
            'gateways' => Gateway::query()
                ->orderBy('gateway_id')
                ->get(['gateway_id', 'name'])
                ->map(fn (Gateway $gateway): array => [
                    'id' => $gateway->gateway_id,
                    'name' => $gateway->name ?: $gateway->gateway_id,
                ])
                ->values()
                ->all(),
            'nodes' => Node::query()
                ->orderBy('gateway_id')
                ->orderBy('node_id')
                ->get(['gateway_id', 'node_id', 'name'])
                ->map(fn (Node $node): array => [
                    'id' => $node->node_id,
                    'gateway_id' => $node->gateway_id,
                    'name' => $node->name ?: $node->node_id,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $metrics
     * @return array{nodes: Collection<int, object>, truncated: bool}
     */
    private function resolveChartNodePairs(string $gatewayId, string $nodeId, array $metrics, Carbon $from, int $maxNodes): array
    {
        $nodes = Node::query()
            ->when($gatewayId !== '', fn ($query) => $query->where('gateway_id', $gatewayId))
            ->when($nodeId !== '', fn ($query) => $query->where('node_id', $nodeId))
            ->orderByDesc('last_seen')
            ->limit($maxNodes + 1)
            ->get(['gateway_id', 'node_id', 'name', 'last_seen']);

        if ($nodes->isEmpty()) {
            $nodes = TelemetryReading::query()
                ->select('gateway_id', 'node_id')
                ->selectRaw('MAX(timestamp) as last_seen')
                ->whereIn('metric_key', $metrics)
                ->where('timestamp', '>=', $from)
                ->when($gatewayId !== '', fn ($query) => $query->where('gateway_id', $gatewayId))
                ->when($nodeId !== '', fn ($query) => $query->where('node_id', $nodeId))
                ->groupBy('gateway_id', 'node_id')
                ->orderByDesc('last_seen')
                ->limit($maxNodes + 1)
                ->get()
                ->map(function (TelemetryReading $row): object {
                    return (object) [
                        'gateway_id' => $row->gateway_id,
                        'node_id' => $row->node_id,
                        'name' => $row->node_id,
                    ];
                });
        }

        $truncated = $nodeId === '' && $nodes->count() > $maxNodes;

        return [
            'nodes' => $nodes->take($maxNodes)->values(),
            'truncated' => $truncated,
        ];
    }

    private function chartSeriesLabel(object $pair, string $metric): string
    {
        $nodeName = filled($pair->name ?? null) ? (string) $pair->name : (string) $pair->node_id;
        $metricLabel = $metric === 'humidity' ? __('ui.dashboard.hum') : __('ui.dashboard.temp');

        return $pair->gateway_id.' / '.$nodeName.' · '.$metricLabel;
    }

    private function parseSince(string $since, Carbon $floor): ?Carbon
    {
        if ($since === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($since);
        } catch (\Throwable) {
            return null;
        }

        return $parsed->lt($floor) ? $floor : $parsed;
    }

    /**
     * Combine per-node sequence PDR for the given telemetry query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Telemetry>  $query
     * @return array{received: int, expected: int, lost: int, ratio: float}|null
     */
    private function packetDeliveryRatioFor($query): ?array
    {
        $packets = (clone $query)
            ->reorder()
            ->whereNotNull('seq')
            ->orderBy('gateway_id')
            ->orderBy('node_id')
            ->orderBy('timestamp')
            ->get(['gateway_id', 'node_id', 'seq']);

        if ($packets->isEmpty()) {
            return null;
        }

        $received = 0;
        $expected = 0;

        foreach ($packets->groupBy(fn (Telemetry $row): string => $row->gateway_id.'|'.$row->node_id) as $group) {
            $pdr = PacketDeliveryRatio::fromSequences($group->pluck('seq'));
            if ($pdr === null) {
                continue;
            }

            $received += $pdr['received'];
            $expected += $pdr['expected'];
        }

        if ($expected <= 0) {
            return null;
        }

        return [
            'received' => $received,
            'expected' => $expected,
            'lost' => max(0, $expected - $received),
            'ratio' => $received / $expected,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function kpiPayload(): array
    {
        $threshold = now()->subMinutes(15);
        $onlineNodeCount = Node::query()->where('last_seen', '>=', $threshold)->count();
        $offlineNodeCount = Node::query()
            ->where(fn ($q) => $q->whereNull('last_seen')->orWhere('last_seen', '<', $threshold))
            ->count();
        $todayPacketCount = Telemetry::query()->whereDate('timestamp', today())->count();
        $todayPdr = $this->packetDeliveryRatioFor(
            Telemetry::query()->whereDate('timestamp', today())
        );

        return [
            'gatewayCount' => Gateway::query()->count(),
            'nodeCount' => Node::query()->count(),
            'onlineNodeCount' => $onlineNodeCount,
            'offlineNodeCount' => $offlineNodeCount,
            'todayPacketCount' => $todayPacketCount,
            'packetCount' => Telemetry::query()->count(),
            'todayPdrText' => $todayPdr === null
                ? '—'
                : number_format($todayPdr['ratio'] * 100, 1).'%',
            'todayPdrMeta' => $todayPdr === null
                ? __('ui.dashboard.pdr_unavailable')
                : __('ui.dashboard.pdr_today_meta', [
                    'received' => number_format($todayPdr['received']),
                    'expected' => number_format($todayPdr['expected']),
                ]),
            'statusText' => __('ui.dashboard.status_online', [
                'online' => number_format($onlineNodeCount),
                'offline' => number_format($offlineNodeCount),
                'today' => number_format($todayPacketCount),
            ]),
        ];
    }

    public function telemetry(Request $request): View
    {
        $query = Telemetry::query()->latest('timestamp');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('gateway_id', 'like', "%{$search}%")
                    ->orWhere('node_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('gateway_id')) {
            $query->where('gateway_id', $request->string('gateway_id')->toString());
        }

        if ($request->filled('node_id')) {
            $query->where('node_id', $request->string('node_id')->toString());
        }

        if ($request->filled('metric_key')) {
            $metricKey = $request->string('metric_key')->toString();
            $query->whereHas('readings', fn ($q) => $q->where('metric_key', $metricKey));
        }

        $pdr = $this->packetDeliveryRatioFor($query);
        $telemetry = $query->paginate(15)->withQueryString();

        $metricKeys = TelemetryReading::query()
            ->select('metric_key')
            ->distinct()
            ->orderBy('metric_key')
            ->pluck('metric_key');

        return view('telemetry.index', [
            'telemetry' => $telemetry,
            'gateways' => Gateway::query()->orderBy('gateway_id')->get(),
            'nodes' => Node::query()->orderBy('node_id')->get(),
            'metricKeys' => $metricKeys,
            'pdr' => $pdr,
        ]);
    }

    public function exportTelemetry(Request $request): StreamedResponse
    {
        $query = TelemetryReading::query()->latest('timestamp');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('gateway_id', 'like', "%{$search}%")
                    ->orWhere('node_id', 'like', "%{$search}%")
                    ->orWhere('metric_key', 'like', "%{$search}%");
            });
        }

        if ($request->filled('gateway_id')) {
            $query->where('gateway_id', $request->string('gateway_id')->toString());
        }

        if ($request->filled('node_id')) {
            $query->where('node_id', $request->string('node_id')->toString());
        }

        if ($request->filled('metric_key')) {
            $query->where('metric_key', $request->string('metric_key')->toString());
        }

        $filename = 'telemetry-readings-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['timestamp', 'gateway_id', 'node_id', 'metric_key', 'metric_value', 'unit']);

            $query->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        optional($row->timestamp)?->toIso8601String(),
                        $row->gateway_id,
                        $row->node_id,
                        $row->metric_key,
                        $row->metric_value,
                        $row->unit,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function logs(Request $request): View
    {
        $query = GatewayLog::query()->latest('created_at');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('gateway_id', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($request->filled('level')) {
            $query->where('level', $request->string('level')->toString());
        }

        return view('logs.index', [
            'logs' => $query->paginate(20)->withQueryString(),
        ]);
    }
}
