@extends('layouts.app')

@section('title', __('ui.dashboard.title'))
@section('kicker', __('ui.dashboard.kicker'))
@section('heading', __('ui.dashboard.title'))
@section('subtitle', __('ui.dashboard.subtitle'))

@section('content')
<div
    id="dashboardLive"
    data-kpis-url="{{ route('dashboard.widgets.kpis') }}"
    data-telemetry-url="{{ route('dashboard.widgets.telemetry') }}"
    data-logs-url="{{ route('dashboard.widgets.logs') }}"
    @if ($showEnvGraph)
        data-chart-url="{{ route('dashboard.widgets.chart') }}"
        data-chart-temp="{{ $showTemperatureGraph ? '1' : '0' }}"
        data-chart-hum="{{ $showHumidityGraph ? '1' : '0' }}"
        data-chart-window="{{ (int) config('lora.graph_window_minutes', 30) }}"
        data-chart-max="{{ (int) config('lora.graph_max_series', 8) }}"
        data-chart-empty="{{ __('ui.dashboard.chart_empty') }}"
        data-chart-truncated="{{ __('ui.dashboard.chart_truncated', ['count' => (int) config('lora.graph_max_series', 8)]) }}"
    @endif
>
<div class="status-strip" data-widget="kpis">
    <span class="status-dot is-live" title="{{ __('ui.dashboard.live') }}"></span>
    <strong data-bind="statusText">
        {{ __('ui.dashboard.status_online', [
            'online' => number_format($onlineNodeCount),
            'offline' => number_format($offlineNodeCount),
            'today' => number_format($todayPacketCount),
        ]) }}
    </strong>
    <span class="text-secondary">{{ __('ui.dashboard.threshold_note') }}</span>
    <span class="live-badge">{{ __('ui.dashboard.live') }}</span>
</div>

<div class="row row-deck row-cards mb-3" data-widget="kpis">
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader">{{ __('ui.dashboard.gateway') }}</div>
                <div class="h1 mb-1" data-bind="gatewayCount">{{ number_format($gatewayCount) }}</div>
                <div class="text-secondary">{{ __('ui.dashboard.gateway_meta') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader">{{ __('ui.dashboard.node') }}</div>
                <div class="h1 mb-1" data-bind="nodeCount">{{ number_format($nodeCount) }}</div>
                <div class="text-secondary">{{ __('ui.dashboard.node_meta') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader">{{ __('ui.dashboard.node_online') }}</div>
                <div class="h1 mb-1 text-success" data-bind="onlineNodeCount">{{ number_format($onlineNodeCount) }}</div>
                <div class="text-secondary">{{ __('ui.dashboard.node_online_meta') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="subheader">{{ __('ui.dashboard.node_offline') }}</div>
                <div class="h1 mb-1 text-danger" data-bind="offlineNodeCount">{{ number_format($offlineNodeCount) }}</div>
                <div class="text-secondary">{{ __('ui.dashboard.node_offline_meta') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="card">
            <div class="card-body">
                <div class="subheader">{{ __('ui.dashboard.packets_today') }}</div>
                <div class="h1 mb-1" data-bind="todayPacketCount">{{ number_format($todayPacketCount) }}</div>
                <div class="text-secondary">{{ __('ui.dashboard.packets_today_meta') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="card">
            <div class="card-body">
                <div class="subheader">{{ __('ui.dashboard.packets_total') }}</div>
                <div class="h1 mb-1" data-bind="packetCount">{{ number_format($packetCount) }}</div>
                <div class="text-secondary">{{ __('ui.dashboard.packets_total_meta') }}</div>
            </div>
        </div>
    </div>
</div>

@if ($showEnvGraph)
<div class="card mb-3" data-widget="chart">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ __('ui.dashboard.chart_title') }}</h3>
            <div class="card-subtitle">
                {{ __('ui.dashboard.chart_subtitle', ['minutes' => (int) config('lora.graph_window_minutes', 30)]) }}
            </div>
        </div>
        <div class="card-actions chart-filters">
            <select id="chartGatewayFilter" class="form-select form-select-sm" aria-label="{{ __('ui.nav.gateway') }}">
                <option value="">{{ __('ui.dashboard.all_gateways') }}</option>
                @foreach ($chartGateways as $gateway)
                    <option value="{{ $gateway->gateway_id }}">{{ $gateway->name ?: $gateway->gateway_id }}</option>
                @endforeach
            </select>
            <select id="chartNodeFilter" class="form-select form-select-sm" aria-label="{{ __('ui.nav.node') }}">
                <option value="">{{ __('ui.dashboard.all_nodes') }}</option>
                @foreach ($chartNodes as $node)
                    <option value="{{ $node->node_id }}" data-gateway="{{ $node->gateway_id }}">
                        {{ $node->gateway_id }} / {{ $node->name ?: $node->node_id }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="card-body">
        <div class="chart-meta">
            <div class="chart-last-values" data-bind="chartLastValues"></div>
            <div class="text-secondary" data-bind="chartNote" style="font-size:12px;"></div>
        </div>
        <div class="chart-canvas-wrap">
            <canvas id="envChart"></canvas>
            <div class="chart-empty text-secondary" id="envChartEmpty">{{ __('ui.dashboard.chart_empty') }}</div>
        </div>
    </div>
</div>
@endif

<div class="row row-deck row-cards">
    <div class="col-lg-7">
        <div class="card" data-widget="telemetry">
            <div class="card-header">
                <h3 class="card-title">{{ __('ui.dashboard.recent_telemetry') }}</h3>
                <div class="card-actions">
                    <a href="{{ route('telemetry.index') }}" class="btn btn-sm btn-ghost-secondary">{{ __('ui.dashboard.view_all') }}</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.dashboard.time') }}</th>
                            <th>{{ __('ui.nav.node') }}</th>
                            <th>{{ __('ui.telemetry.metrics') }}</th>
                            <th>RSSI</th>
                        </tr>
                    </thead>
                    <tbody data-bind-rows="telemetry">
                        @forelse ($recentTelemetry as $item)
                            <tr>
                                <td class="mono">{{ $item->timestamp?->diffForHumans() }}</td>
                                <td>
                                    <div class="mono">{{ $item->node_id }}</div>
                                    <div class="text-secondary small">{{ $item->gateway_id }}</div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($item->displayMetrics() as $key => $value)
                                            <span class="badge bg-azure-lt mono">{{ $key }}: {{ $value }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="mono">{{ $item->rssi ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="text-secondary">{{ __('ui.dashboard.no_telemetry') }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card" data-widget="logs">
            <div class="card-header">
                <h3 class="card-title">{{ __('ui.dashboard.recent_logs') }}</h3>
                <div class="card-actions">
                    <a href="{{ route('logs.index') }}" class="btn btn-sm btn-ghost-secondary">{{ __('ui.dashboard.view_all') }}</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>{{ __('ui.dashboard.event') }}</th>
                            <th>{{ __('ui.dashboard.level') }}</th>
                        </tr>
                    </thead>
                    <tbody data-bind-rows="logs">
                        @forelse ($recentLogs as $log)
                            <tr>
                                <td>
                                    <div class="mono">{{ $log->event }}</div>
                                    <div class="text-secondary small">{{ \Illuminate\Support\Str::limit($log->message, 70) }}</div>
                                    <div class="text-secondary" style="font-size:11px;">{{ $log->created_at?->diffForHumans() }}</div>
                                </td>
                                <td>
                                    @php
                                        $levelClass = match ($log->level) {
                                            'warning' => 'bg-warning-lt',
                                            'error' => 'bg-danger-lt',
                                            default => 'bg-azure-lt',
                                        };
                                    @endphp
                                    <span class="badge {{ $levelClass }}">{{ $log->level }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">
                                    <div class="empty-state">
                                        <div class="text-secondary">{{ __('ui.dashboard.no_logs') }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@if ($showEnvGraph)
@push('pre-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
@endpush
@endif
