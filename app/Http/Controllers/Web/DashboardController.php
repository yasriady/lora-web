<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\GatewayLog;
use App\Models\Node;
use App\Models\Telemetry;
use App\Models\TelemetryReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'recentTelemetry' => Telemetry::query()->latest('timestamp')->limit(8)->get(),
            'recentLogs' => GatewayLog::query()->latest('created_at')->limit(8)->get(),
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

        return [
            'gatewayCount' => Gateway::query()->count(),
            'nodeCount' => Node::query()->count(),
            'onlineNodeCount' => $onlineNodeCount,
            'offlineNodeCount' => $offlineNodeCount,
            'todayPacketCount' => $todayPacketCount,
            'packetCount' => Telemetry::query()->count(),
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
