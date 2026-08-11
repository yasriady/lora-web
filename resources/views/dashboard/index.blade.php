@extends('layouts.app')

@section('title', __('ui.dashboard.title'))
@section('kicker', __('ui.dashboard.kicker'))
@section('heading', __('ui.dashboard.title'))
@section('subtitle', __('ui.dashboard.subtitle'))

@section('content')
<div class="status-strip">
    <span class="status-dot"></span>
    <strong>
        {{ __('ui.dashboard.status_online', [
            'online' => number_format($onlineNodeCount),
            'offline' => number_format($offlineNodeCount),
            'today' => number_format($todayPacketCount),
        ]) }}
    </strong>
    <span class="muted">{{ __('ui.dashboard.threshold_note') }}</span>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-label">{{ __('ui.dashboard.gateway') }}</div>
        <div class="kpi-value">{{ number_format($gatewayCount) }}</div>
        <div class="kpi-meta">{{ __('ui.dashboard.gateway_meta') }}</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">{{ __('ui.dashboard.node') }}</div>
        <div class="kpi-value">{{ number_format($nodeCount) }}</div>
        <div class="kpi-meta">{{ __('ui.dashboard.node_meta') }}</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">{{ __('ui.dashboard.node_online') }}</div>
        <div class="kpi-value" style="color: var(--success);">{{ number_format($onlineNodeCount) }}</div>
        <div class="kpi-meta">{{ __('ui.dashboard.node_online_meta') }}</div>
    </div>
    <div class="kpi warning">
        <div class="kpi-label">{{ __('ui.dashboard.node_offline') }}</div>
        <div class="kpi-value" style="color: var(--amber);">{{ number_format($offlineNodeCount) }}</div>
        <div class="kpi-meta">{{ __('ui.dashboard.node_offline_meta') }}</div>
    </div>
</div>

<div class="kpi-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
    <div class="kpi">
        <div class="kpi-label">{{ __('ui.dashboard.packets_today') }}</div>
        <div class="kpi-value">{{ number_format($todayPacketCount) }}</div>
        <div class="kpi-meta">{{ __('ui.dashboard.packets_today_meta') }}</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">{{ __('ui.dashboard.packets_total') }}</div>
        <div class="kpi-value">{{ number_format($packetCount) }}</div>
        <div class="kpi-meta">{{ __('ui.dashboard.packets_total_meta') }}</div>
    </div>
</div>

<div class="split-grid mt-1">
    <section class="panel">
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
                <tbody>
                    @forelse ($recentTelemetry as $item)
                        <tr>
                            <td class="mono">{{ $item->timestamp?->diffForHumans() }}</td>
                            <td>
                                <div class="mono">{{ $item->node_id }}</div>
                                <div class="muted" style="font-size:0.75rem;">{{ $item->gateway_id }}</div>
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

    <section class="panel">
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
                <tbody>
                    @forelse ($recentLogs as $log)
                        <tr>
                            <td>
                                <div class="mono">{{ $log->event }}</div>
                                <div class="muted" style="font-size:0.78rem;">{{ \Illuminate\Support\Str::limit($log->message, 70) }}</div>
                                <div class="muted" style="font-size:0.72rem;">{{ $log->created_at?->diffForHumans() }}</div>
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
@endsection
