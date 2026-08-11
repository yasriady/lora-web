<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\GatewayLog;
use App\Models\Node;
use App\Models\Telemetry;
use App\Models\TelemetryReading;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(): View
    {
        $threshold = now()->subMinutes(15);

        return view('dashboard.index', [
            'gatewayCount' => Gateway::query()->count(),
            'nodeCount' => Node::query()->count(),
            'onlineNodeCount' => Node::query()->where('last_seen', '>=', $threshold)->count(),
            'offlineNodeCount' => Node::query()
                ->where(fn ($q) => $q->whereNull('last_seen')->orWhere('last_seen', '<', $threshold))
                ->count(),
            'todayPacketCount' => Telemetry::query()->whereDate('timestamp', today())->count(),
            'packetCount' => Telemetry::query()->count(),
            'recentTelemetry' => Telemetry::query()->latest('timestamp')->limit(8)->get(),
            'recentLogs' => GatewayLog::query()->latest('created_at')->limit(8)->get(),
            'threshold' => $threshold,
        ]);
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
