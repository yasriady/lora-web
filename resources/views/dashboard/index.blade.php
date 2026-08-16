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
    <span class="muted">{{ __('ui.dashboard.threshold_note') }}</span>
    <span class="live-badge">{{ __('ui.dashboard.live') }}</span>
</div>

<div class="kpi-grid" data-widget="kpis">
    <div class="kpi bg-aqua">
        <div class="kpi-inner">
            <div class="kpi-value" data-bind="gatewayCount">{{ number_format($gatewayCount) }}</div>
            <div class="kpi-label">{{ __('ui.dashboard.gateway') }}</div>
            <div class="kpi-meta">{{ __('ui.dashboard.gateway_meta') }}</div>
        </div>
    </div>
    <div class="kpi bg-green">
        <div class="kpi-inner">
            <div class="kpi-value" data-bind="nodeCount">{{ number_format($nodeCount) }}</div>
            <div class="kpi-label">{{ __('ui.dashboard.node') }}</div>
            <div class="kpi-meta">{{ __('ui.dashboard.node_meta') }}</div>
        </div>
    </div>
    <div class="kpi bg-yellow">
        <div class="kpi-inner">
            <div class="kpi-value" data-bind="onlineNodeCount">{{ number_format($onlineNodeCount) }}</div>
            <div class="kpi-label">{{ __('ui.dashboard.node_online') }}</div>
            <div class="kpi-meta">{{ __('ui.dashboard.node_online_meta') }}</div>
        </div>
    </div>
    <div class="kpi bg-red">
        <div class="kpi-inner">
            <div class="kpi-value" data-bind="offlineNodeCount">{{ number_format($offlineNodeCount) }}</div>
            <div class="kpi-label">{{ __('ui.dashboard.node_offline') }}</div>
            <div class="kpi-meta">{{ __('ui.dashboard.node_offline_meta') }}</div>
        </div>
    </div>
</div>

<div class="kpi-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));" data-widget="kpis">
    <div class="kpi bg-aqua">
        <div class="kpi-inner">
            <div class="kpi-value" data-bind="todayPacketCount">{{ number_format($todayPacketCount) }}</div>
            <div class="kpi-label">{{ __('ui.dashboard.packets_today') }}</div>
            <div class="kpi-meta">{{ __('ui.dashboard.packets_today_meta') }}</div>
        </div>
    </div>
    <div class="kpi bg-green">
        <div class="kpi-inner">
            <div class="kpi-value" data-bind="packetCount">{{ number_format($packetCount) }}</div>
            <div class="kpi-label">{{ __('ui.dashboard.packets_total') }}</div>
            <div class="kpi-meta">{{ __('ui.dashboard.packets_total_meta') }}</div>
        </div>
    </div>
</div>

@if ($showEnvGraph)
<section class="panel chart-panel" data-widget="chart">
    <div class="panel-header chart-panel-header">
        <div>
            <h2 class="panel-title">{{ __('ui.dashboard.chart_title') }}</h2>
            <div class="muted" style="font-size:12px;margin-top:4px;">
                {{ __('ui.dashboard.chart_subtitle', ['minutes' => (int) config('lora.graph_window_minutes', 30)]) }}
            </div>
        </div>
        <div class="chart-filters">
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
    <div class="chart-meta">
        <div class="chart-last-values" data-bind="chartLastValues"></div>
        <div class="muted" data-bind="chartNote" style="font-size:12px;"></div>
    </div>
    <div class="chart-canvas-wrap">
        <canvas id="envChart" height="110"></canvas>
        <div class="chart-empty muted" id="envChartEmpty">{{ __('ui.dashboard.chart_empty') }}</div>
    </div>
</section>
@endif

<div class="split-grid">
    <section class="panel" data-widget="telemetry">
        <div class="panel-header">
            <h2 class="panel-title">{{ __('ui.dashboard.recent_telemetry') }}</h2>
            <a href="{{ route('telemetry.index') }}" class="btn btn-sm btn-soft">{{ __('ui.dashboard.view_all') }}</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
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
                                <div class="muted" style="font-size:12px;">{{ $item->gateway_id }}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach ($item->displayMetrics() as $key => $value)
                                        <span class="badge-pill badge-info mono">{{ $key }}: {{ $value }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="mono">{{ $item->rssi ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <div class="muted">{{ __('ui.dashboard.no_telemetry') }}</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" data-widget="logs">
        <div class="panel-header">
            <h2 class="panel-title">{{ __('ui.dashboard.recent_logs') }}</h2>
            <a href="{{ route('logs.index') }}" class="btn btn-sm btn-soft">{{ __('ui.dashboard.view_all') }}</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
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
                                <div class="muted" style="font-size:12px;">{{ \Illuminate\Support\Str::limit($log->message, 70) }}</div>
                                <div class="muted" style="font-size:11px;">{{ $log->created_at?->diffForHumans() }}</div>
                            </td>
                            <td>
                                @php
                                    $levelClass = match ($log->level) {
                                        'warning' => 'badge-offline',
                                        'error' => 'badge-danger',
                                        default => 'badge-info',
                                    };
                                @endphp
                                <span class="badge-pill {{ $levelClass }}">{{ $log->level }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">
                                <div class="empty-state">
                                    <div class="muted">{{ __('ui.dashboard.no_logs') }}</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
@endsection

@if ($showEnvGraph)
@push('pre-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
@endpush
@endif
